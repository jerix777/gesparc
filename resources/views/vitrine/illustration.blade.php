{{-- Illustrations vectorielles originales (libres de droits) pour le carrousel de la vitrine. --}}
<svg viewBox="0 0 600 460" class="illu" role="img" aria-label="{{ ['balance' => 'Balance de la justice', 'palais' => 'Palais de justice', 'maillet' => 'Maillet de juge', 'parc' => 'Matériel informatique'][$name] ?? '' }}">
  <defs>
    <radialGradient id="halo-{{ $name }}" cx="50%" cy="45%" r="50%"><stop offset="0" stop-color="#f2cf75" stop-opacity=".35"/><stop offset="1" stop-color="#f2cf75" stop-opacity="0"/></radialGradient>
    <linearGradient id="gold-{{ $name }}" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#f6dc93"/><stop offset="1" stop-color="#c5a45b"/></linearGradient>
  </defs>
  <circle cx="300" cy="215" r="210" fill="url(#halo-{{ $name }})"/>
  <circle cx="300" cy="215" r="170" fill="none" stroke="#ffffff22" stroke-width="2" stroke-dasharray="4 10"/>
  @switch($name)
    @case('balance')
      <g fill="url(#gold-balance)" stroke="#8a6d2c" stroke-width="2">
        <rect x="292" y="95" width="16" height="250" rx="4"/>
        <circle cx="300" cy="88" r="16"/>
        <rect x="140" y="118" width="320" height="12" rx="6" transform="rotate(-4 300 124)"/>
        <path d="M230 352h140l20 30H210z"/>
        <rect x="190" y="382" width="220" height="16" rx="5"/>
      </g>
      <g stroke="#f2cf75" stroke-width="2.5" fill="none">
        <path d="M165 133L120 245M165 133L210 245"/>
        <path d="M435 112L390 222M435 112L480 222"/>
      </g>
      <g fill="url(#gold-balance)" stroke="#8a6d2c" stroke-width="2">
        <path d="M105 245h120a60 22 0 0 1-120 0z"/>
        <path d="M375 222h120a60 22 0 0 1-120 0z"/>
        <circle cx="165" cy="133" r="7"/><circle cx="435" cy="112" r="7"/>
      </g>
      <g fill="#ffffff" opacity=".9"><rect x="138" y="225" width="54" height="14" rx="3"/><rect x="146" y="211" width="38" height="14" rx="3" opacity=".7"/></g>
      @break
    @case('palais')
      <g fill="#ffffff">
        <path d="M120 175L300 90L480 175z" fill="url(#gold-palais)" stroke="#8a6d2c" stroke-width="2"/>
        <rect x="130" y="175" width="340" height="18" rx="3" opacity=".95"/>
        @foreach ([150, 205, 260, 315, 370, 425] as $x)
          <rect x="{{ $x }}" y="200" width="26" height="150" rx="3" opacity=".92"/>
          <rect x="{{ $x - 5 }}" y="196" width="36" height="8" rx="2" opacity=".75"/>
          <rect x="{{ $x - 5 }}" y="346" width="36" height="8" rx="2" opacity=".75"/>
        @endforeach
        <rect x="115" y="356" width="370" height="14" rx="3" opacity=".85"/>
        <rect x="100" y="372" width="400" height="14" rx="3" opacity=".7"/>
        <rect x="85" y="388" width="430" height="14" rx="3" opacity=".55"/>
      </g>
      <circle cx="300" cy="145" r="16" fill="none" stroke="#8a6d2c" stroke-width="3"/>
      <path d="M292 145h16M300 137v16" stroke="#8a6d2c" stroke-width="3"/>
      <g transform="translate(296 40)"><rect x="0" y="0" width="3" height="52" fill="#ffffff"/><rect x="3" y="2" width="14" height="22" fill="#f77f00"/><rect x="17" y="2" width="14" height="22" fill="#ffffff"/><rect x="31" y="2" width="14" height="22" fill="#009e60"/></g>
      @break
    @case('maillet')
      <g transform="rotate(-30 300 210)">
        <rect x="200" y="150" width="200" height="70" rx="14" fill="url(#gold-maillet)" stroke="#8a6d2c" stroke-width="2"/>
        <rect x="186" y="143" width="22" height="84" rx="6" fill="#f6dc93" stroke="#8a6d2c" stroke-width="2"/>
        <rect x="392" y="143" width="22" height="84" rx="6" fill="#f6dc93" stroke="#8a6d2c" stroke-width="2"/>
        <rect x="290" y="218" width="20" height="170" rx="8" fill="#8a5a2b" stroke="#5e3b1a" stroke-width="2"/>
      </g>
      <ellipse cx="380" cy="372" rx="110" ry="22" fill="#8a5a2b" stroke="#5e3b1a" stroke-width="2"/>
      <rect x="270" y="350" width="220" height="22" rx="8" fill="#a8743d" stroke="#5e3b1a" stroke-width="2"/>
      <g fill="#ffffff"><rect x="80" y="300" width="150" height="100" rx="6" opacity=".95" transform="rotate(-6 155 350)"/><rect x="92" y="290" width="150" height="100" rx="6" opacity=".7" transform="rotate(4 167 340)"/></g>
      <g stroke="#173d68" stroke-width="5" stroke-linecap="round" opacity=".5"><path d="M110 325h80M110 345h95M110 365h60" transform="rotate(-6 155 350)"/></g>
      <path d="M150 90l12 24 26 4-19 18 5 26-24-12-24 12 5-26-19-18 26-4z" fill="#f2cf75" opacity=".8"/>
      @break
    @default
      <g stroke="#8a6d2c" stroke-width="2">
        <rect x="185" y="95" width="230" height="150" rx="12" fill="#ffffff"/>
        <rect x="200" y="110" width="200" height="120" rx="6" fill="#173d68" stroke="none"/>
        <path d="M280 245h40l10 34h-60z" fill="url(#gold-parc)"/>
        <rect x="240" y="279" width="120" height="12" rx="4" fill="url(#gold-parc)"/>
      </g>
      <g fill="#f2cf75"><rect x="216" y="190" width="18" height="28" rx="2"/><rect x="242" y="170" width="18" height="48" rx="2"/><rect x="268" y="180" width="18" height="38" rx="2"/><rect x="294" y="150" width="18" height="68" rx="2"/></g>
      <path d="M330 175l18 18 34-36" fill="none" stroke="#3ddc97" stroke-width="8" stroke-linecap="round" stroke-linejoin="round"/>
      <g stroke="#8a6d2c" stroke-width="2">
        <rect x="60" y="300" width="140" height="80" rx="10" fill="#ffffff"/><rect x="80" y="285" width="100" height="20" rx="4" fill="#f6dc93"/><rect x="85" y="340" width="90" height="26" rx="3" fill="#e8eef5"/>
        <rect x="430" y="290" width="90" height="110" rx="10" fill="#ffffff"/><circle cx="475" cy="320" r="10" fill="#3ddc97"/><rect x="450" y="345" width="50" height="8" rx="3" fill="#e8eef5"/><rect x="450" y="362" width="50" height="8" rx="3" fill="#e8eef5"/>
      </g>
      <g stroke="#f2cf75" stroke-width="3" fill="none" stroke-dasharray="6 8"><path d="M200 330C240 330 250 300 285 291"/><path d="M430 340C390 340 370 300 330 291"/></g>
  @endswitch
  <g opacity=".9"><rect x="150" y="430" width="100" height="6" rx="3" fill="#f77f00"/><rect x="250" y="430" width="100" height="6" rx="3" fill="#ffffff"/><rect x="350" y="430" width="100" height="6" rx="3" fill="#009e60"/></g>
</svg>
