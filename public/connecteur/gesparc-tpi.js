/*!
 * Connecteur GesParc pour l'application « Gestion du personnel – TPI ».
 *
 * À charger APRÈS le script principal de l'application TPI :
 *   <script>window.GESPARC_URL = 'https://gesparc.exemple.ci';<\/script>
 *   <script src="https://gesparc.exemple.ci/connecteur/gesparc-tpi.js"><\/script>
 *
 * Effets, une fois l'agent connecté à GesParc (une fois par poste, jeton valable 30 jours) :
 *  - « Demande de matériels informatiques » : les demandes partent sur le serveur GesParc (plus dans le navigateur),
 *    l'agent voit le matériel qui lui est affecté et peut signaler une panne ;
 *    le Service informatique traite les demandes de toutes les juridictions depuis le serveur.
 *  - « Parc informatique et accessoires » : l'inventaire du serveur s'affiche en tête de page.
 * Sans connexion, l'application fonctionne exactement comme avant.
 */
(function () {
  'use strict';

  var KEY = 'gesparc_connexions';
  var origMatRender = window.matRender;
  var origModRender = window.modRender;
  if (typeof origMatRender !== 'function') {
    console.warn('[GesParc] matRender introuvable : connecteur inactif.');
    return;
  }

  // --- Outils -------------------------------------------------------------------------------
  function h(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function agentId() {
    try { return currentAgent ? String(currentAgent.id) : null; } catch (e) { return null; }
  }
  function store() {
    try { return JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) { return {}; }
  }
  function saveStore(s) {
    try { localStorage.setItem(KEY, JSON.stringify(s)); } catch (e) { /* stockage plein */ }
  }
  function session() {
    var id = agentId();
    var s = id && store()[id];
    if (!s) return null;
    if (s.expire && Date.parse(s.expire) < Date.now()) { forget(); return null; }
    return s;
  }
  function forget() {
    var s = store(); delete s[agentId()]; saveStore(s);
  }
  function serverUrl() {
    return (window.GESPARC_URL || (session() || {}).url || '').replace(/\/+$/, '');
  }
  function api(method, path, body) {
    var s = session();
    return fetch(s.url + '/api/v1' + path, {
      method: method,
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + s.jeton },
      body: body ? JSON.stringify(body) : undefined
    }).then(function (r) {
      if (r.status === 401) { forget(); rerender(); throw new Error('Session GesParc expirée : reconnectez-vous.'); }
      if (r.status === 204) return null;
      return r.json().then(function (j) {
        if (!r.ok) {
          var msg = j && (j.errors ? Object.values(j.errors)[0][0] : j.message);
          throw new Error(msg || ('Erreur ' + r.status));
        }
        return j;
      });
    });
  }
  function fmt(iso) {
    if (!iso) return '';
    var d = new Date(iso);
    return d.toLocaleDateString('fr-FR') + ' ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  }
  function pill(statut, libelle) {
    var c = { en_attente: '#a56b12', accordee: '#16845b', refusee: '#b83a45', affecte: '#16845b', en_panne: '#b83a45', en_maintenance: '#a56b12', en_stock: '#1f5fa8', reforme: '#6b7280', ouvert: '#b83a45' }[statut] || '#475569';
    return '<span style="display:inline-block;padding:2px 9px;border-radius:20px;font-size:12px;font-weight:600;color:' + c + ';background:' + c + '1a">' + h(libelle) + '</span>';
  }
  function rerender() {
    try { matRender(); } catch (e) { console.error(e); }
  }
  // Même liste que le serveur (MaterialRequest::MATERIELS) ; reprise de l'application TPI.
  var types = ['Clavier', 'Souris', 'Écran', 'Ordinateur portable', 'Ordinateur de bureau', 'Unité centrale', 'Onduleur',
    'Disque dur externe', 'Clé USB', 'Câble réseau RJ45', 'Câble HDMI', 'Câble VGA', 'Chargeur d’ordinateur',
    'Batterie d’ordinateur', 'Adaptateur', 'Multiprise', 'Cartouche d’encre', 'Autres accessoires informatiques'];

  // --- Connexion ----------------------------------------------------------------------------
  function loginPanel() {
    var url = serverUrl();
    return '<div class="panel" style="border-left:5px solid #c5a45b"><h2>Serveur GesParc</h2>' +
      '<p class="sub">Connectez-vous une fois avec votre compte GesParc : vos demandes seront transmises au serveur central du parc informatique.</p>' +
      '<div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px">' +
      (window.GESPARC_URL ? '' : '<div class="field"><label>Adresse du serveur</label><input id="gp_url" placeholder="https://gesparc.exemple.ci" value="' + h(url) + '"></div>') +
      '<div class="field"><label>E-mail GesParc</label><input id="gp_email" type="email"></div>' +
      '<div class="field"><label>Mot de passe</label><input id="gp_pwd" type="password"></div></div>' +
      '<div class="actions"><button id="gp_login" type="button">Se connecter à GesParc</button> <span id="gp_msg" class="muted"></span></div></div>';
  }
  function bindLogin() {
    var b = document.getElementById('gp_login');
    if (!b) return;
    b.onclick = function () {
      var url = (window.GESPARC_URL || (document.getElementById('gp_url') || {}).value || '').trim().replace(/\/+$/, '');
      var email = document.getElementById('gp_email').value.trim();
      var pwd = document.getElementById('gp_pwd').value;
      var msg = document.getElementById('gp_msg');
      if (!/^https?:\/\//.test(url)) { msg.textContent = 'Indiquez l’adresse du serveur (https://…).'; return; }
      b.disabled = true; msg.textContent = 'Connexion…';
      fetch(url + '/api/v1/jetons', {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: email, password: pwd, appareil: 'Application TPI' })
      }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (res) {
          if (!res.ok) throw new Error(res.j.errors ? Object.values(res.j.errors)[0][0] : (res.j.message || 'Connexion refusée'));
          var s = store();
          s[agentId()] = { url: url, jeton: res.j.jeton, expire: res.j.expire_le, utilisateur: res.j.utilisateur };
          saveStore(s);
          rerender();
        })
        .catch(function (e) { msg.textContent = e.message === 'Failed to fetch' ? 'Serveur injoignable.' : e.message; b.disabled = false; });
    };
  }

  // --- Demandes de matériel (remplace l'écran « matRoot » quand l'agent est connecté) -----
  window.matRender = function () {
    var root = document.getElementById('matRoot');
    if (!root || !agentId()) return origMatRender.apply(this, arguments);
    var s = session();
    if (!s) {
      var r = origMatRender.apply(this, arguments);
      root.insertAdjacentHTML('afterbegin', loginPanel());
      bindLogin();
      return r;
    }
    var u = s.utilisateur || {};
    root.innerHTML =
      '<div class="panel" style="border-left:5px solid #16845b;display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center">' +
      '<div>Connecté à GesParc : <b>' + h(u.nom) + '</b> <span class="muted">· ' + h(u.role_libelle) + ' · ' + h(u.institution ? u.institution.nom : 'niveau national') + '</span></div>' +
      '<div class="actions"><a class="secondary" style="text-decoration:none;padding:8px 12px;border-radius:8px;background:#e8eef5;color:#102b4e" target="_blank" rel="noopener" href="' + h(s.url) + '">Ouvrir GesParc</a> <button class="secondary" type="button" id="gp_logout">Se déconnecter</button></div></div>' +
      '<div class="panel"><h2>Nouvelle demande</h2><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px">' +
      '<div class="field"><label>Matériel souhaité</label><select id="gp_mat"><option value="">— Sélectionner —</option>' + types.map(function (t) { return '<option>' + h(t) + '</option>'; }).join('') + '</select></div>' +
      '<div class="field"><label>Quantité</label><input id="gp_qte" type="number" min="1" max="99" value="1"></div>' +
      '<div class="field"><label>Caractéristiques / référence</label><input id="gp_ref" placeholder="Obligatoire pour « Autres accessoires »"></div></div>' +
      '<div class="field"><label>Observation</label><textarea id="gp_obs" rows="2"></textarea></div>' +
      '<div class="actions"><button type="button" id="gp_send">Soumettre la demande</button> <span id="gp_sendmsg" class="muted"></span></div></div>' +
      '<div id="gp_pending"></div><div id="gp_equip"></div><div id="gp_mine"><div class="panel"><div class="muted">Chargement…</div></div></div>';

    document.getElementById('gp_logout').onclick = function () {
      api('DELETE', '/jetons/courant').catch(function () {}).then(function () { forget(); rerender(); });
    };
    document.getElementById('gp_send').onclick = function () {
      var btn = this, msg = document.getElementById('gp_sendmsg');
      btn.disabled = true; msg.textContent = 'Envoi…';
      api('POST', '/demandes', {
        materiel: document.getElementById('gp_mat').value,
        quantite: parseInt(document.getElementById('gp_qte').value, 10),
        caracteristiques: document.getElementById('gp_ref').value.trim() || null,
        observations: document.getElementById('gp_obs').value.trim() || null
      }).then(function (j) {
        alert('Votre demande n° ' + j.donnees.numero + ' a été transmise au Service informatique.');
        rerender();
      }).catch(function (e) { msg.textContent = e.message; btn.disabled = false; });
    };
    loadMine(); loadEquipments();
    api('GET', '/moi').then(function (j) { if (j.droits.traiter_demandes) loadPending(); }).catch(showError);
  };

  function showError(e) {
    var el = document.getElementById('gp_mine');
    if (el) el.innerHTML = '<div class="panel"><div class="muted" style="color:#b83a45">' + h(e.message) + '</div></div>';
  }

  function loadMine() {
    api('GET', '/demandes?miennes=1&limite=50').then(function (j) {
      var rows = j.donnees.map(function (r) {
        return '<tr><td><b>' + h(r.numero) + '</b><div class="muted">' + fmt(r.cree_le) + '</div></td><td><b>' + h(r.materiel) + '</b>' + (r.caracteristiques ? '<div class="muted">' + h(r.caracteristiques) + '</div>' : '') + '</td><td>' + h(r.quantite) + '</td><td>' + pill(r.statut, r.statut_libelle) + (r.motif_refus ? '<div class="muted">Motif : ' + h(r.motif_refus) + '</div>' : '') + '</td></tr>';
      }).join('');
      document.getElementById('gp_mine').innerHTML = '<div class="panel"><h2>Mes demandes (GesParc)</h2>' + (rows
        ? '<div class="tablewrap"><table><thead><tr><th>N° / date</th><th>Matériel</th><th>Qté</th><th>Statut</th></tr></thead><tbody>' + rows + '</tbody></table></div>'
        : '<div class="empty">Aucune demande.</div>') + '</div>';
    }).catch(showError);
  }

  function loadEquipments() {
    api('GET', '/mes-equipements').then(function (j) {
      if (!j.donnees.length) return;
      var rows = j.donnees.map(function (e) {
        var canReport = e.statut === 'affecte';
        return '<tr><td><b>' + h(e.numero_inventaire) + '</b></td><td>' + h(e.designation) + '<div class="muted">' + h([e.type, e.marque, e.modele].filter(Boolean).join(' · ')) + '</div></td><td>' + h(e.numero_serie || '—') + '</td><td>' + pill(e.statut, e.statut_libelle) + '</td><td>' +
          (canReport ? '<button type="button" class="danger" data-gp-panne="' + e.id + '">Signaler une panne</button>' : '') + '</td></tr>';
      }).join('');
      var el = document.getElementById('gp_equip');
      el.innerHTML = '<div class="panel"><h2>Matériel qui m’est affecté</h2><div class="tablewrap"><table><thead><tr><th>N° inventaire</th><th>Équipement</th><th>N° de série</th><th>Statut</th><th></th></tr></thead><tbody>' + rows + '</tbody></table></div></div>';
      el.querySelectorAll('[data-gp-panne]').forEach(function (b) {
        b.onclick = function () {
          var d = prompt('Décrivez la panne :', '');
          if (!d || !d.trim()) return;
          api('POST', '/equipements/' + b.getAttribute('data-gp-panne') + '/pannes', { description: d.trim() })
            .then(function (r) { alert('Panne ' + r.donnees.numero + ' signalée au Service informatique.'); rerender(); })
            .catch(function (e) { alert(e.message); });
        };
      });
    }).catch(function () {});
  }

  function loadPending() {
    api('GET', '/demandes?statut=en_attente&limite=200').then(function (j) {
      var me = (session().utilisateur || {}).id;
      var items = j.donnees.filter(function (r) { return r.demandeur.id !== me; });
      var html = items.map(function (r) {
        return '<div class="req" style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:10px 0;border-bottom:1px solid #dbe3ed"><div><b>' + h(r.demandeur.nom) + '</b><div>' + h(r.numero) + ' · ' + h(r.materiel) + ' × ' + h(r.quantite) + '</div><div>N° de porte / bureau : ' + h(r.bureau || 'non renseigné') + '</div><div class="muted">' + h(r.caracteristiques || '') + (r.observations ? ' · ' + h(r.observations) : '') + '</div><div class="muted">' + fmt(r.cree_le) + '</div></div>' +
          '<div class="actions"><button type="button" class="success" data-gp-ok="' + r.id + '">Accorder</button> <button type="button" class="danger" data-gp-ko="' + r.id + '">Refuser</button></div></div>';
      }).join('') || '<div class="empty">Aucune demande à traiter.</div>';
      var el = document.getElementById('gp_pending');
      el.innerHTML = '<div class="panel"><h2>Demandes à traiter (GesParc) <span class="badge">' + items.length + '</span></h2>' + html + '</div>';
      el.querySelectorAll('[data-gp-ok],[data-gp-ko]').forEach(function (b) {
        b.onclick = function () {
          var ok = b.hasAttribute('data-gp-ok'), id = b.getAttribute(ok ? 'data-gp-ok' : 'data-gp-ko'), body = { decision: ok ? 'accorder' : 'refuser' };
          if (ok) { if (!confirm('Accorder cette demande ? Elle sera clôturée.')) return; }
          else { var m = prompt('Motif du refus (obligatoire) :', ''); if (!m || !m.trim()) return; body.motif_refus = m.trim(); }
          api('POST', '/demandes/' + id + '/decision', body).then(rerender).catch(function (e) { alert(e.message); });
        };
      });
    }).catch(function () {});
  }

  // --- Parc informatique : inventaire du serveur en tête de page ------------------------------
  if (typeof origModRender === 'function') {
    window.modRender = function (k) {
      var r = origModRender.apply(this, arguments);
      if (k === 'it') showInventory();
      return r;
    };
  }
  function showInventory() {
    var host = document.getElementById('modroot_it');
    if (!host) return;
    var box = document.getElementById('gp_inventory');
    if (!box) {
      box = document.createElement('div');
      box.id = 'gp_inventory';
      host.parentNode.insertBefore(box, host);
    }
    if (!session()) {
      box.innerHTML = '<div class="panel"><h2>Inventaire GesParc</h2><div class="muted">Connectez-vous à GesParc depuis la page « Demande de matériels informatiques » pour afficher l’inventaire du serveur.</div></div>';
      return;
    }
    box.innerHTML = '<div class="panel"><h2>Inventaire GesParc</h2><div class="muted">Chargement…</div></div>';
    api('GET', '/equipements?par_page=200').then(function (j) {
      var rows = j.donnees.map(function (e) {
        return '<tr><td><b>' + h(e.numero_inventaire) + '</b></td><td>' + h(e.designation) + '<div class="muted">' + h([e.type, e.marque, e.modele].filter(Boolean).join(' · ')) + '</div></td><td>' + h(e.numero_serie || '—') + '</td><td>' + h([e.service, e.bureau ? 'Bureau ' + e.bureau : ''].filter(Boolean).join(' · ') || '—') + '</td><td>' + h(e.affecte_a ? e.affecte_a.nom : '—') + '</td><td>' + pill(e.statut, e.statut_libelle) + '</td></tr>';
      }).join('');
      box.innerHTML = '<div class="panel"><h2>Inventaire GesParc <span class="muted">(' + j.pagination.total + ' équipements)</span></h2>' +
        '<p class="muted">Lecture seule ici. Pour ajouter, affecter ou réformer un équipement : <a target="_blank" rel="noopener" href="' + h(session().url) + '/inventaire">ouvrir GesParc</a>.</p>' +
        (rows ? '<div class="tablewrap"><table><thead><tr><th>N° inventaire</th><th>Équipement</th><th>N° de série</th><th>Localisation</th><th>Affecté à</th><th>Statut</th></tr></thead><tbody>' + rows + '</tbody></table></div>' : '<div class="empty">Aucun équipement.</div>') + '</div>';
    }).catch(function (e) {
      box.innerHTML = '<div class="panel"><h2>Inventaire GesParc</h2><div class="muted">' + h(e.message) + '</div></div>';
    });
  }

  window.GesParc = { session: session, deconnecter: function () { forget(); rerender(); } };
})();
