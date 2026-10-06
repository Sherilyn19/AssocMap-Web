@props(['title' => 'AssocMap', 'current' => 'home', 'internal' => false])
<x-app-layout :title="$title">
    @if($internal)
    <style>
        /* Public pages share the existing green palette and Inter typography. */
        html:has(.am-landing) { scroll-behavior: auto; }
        .am-public { min-height: 100vh; min-height: 100dvh; display: flex; flex-direction: column; }
        .am-public > main, .am-public > .am-landing { flex: 1; }
        .am-public :is(a, button, input, summary, [tabindex]):focus-visible { outline: 2px solid #1f6e3d; outline-offset: 4px; }
        .am-public-header { position: sticky; top: 0; z-index: 1100; background: rgba(255,255,255,.97); border-bottom: 1px solid #d8e6dd; }
        .am-public-bar { max-width: 80rem; margin: auto; padding: .85rem 2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .75rem 2rem; }
        .am-public-brand { display: inline-flex; align-items: center; gap: .65rem; color: #1f6e3d; font-size: 1.5rem; font-weight: 700; letter-spacing: -.04em; }
        .am-public-brand img { width: 44px; height: 44px; object-fit: contain; }
        .am-public-nav { display: flex; align-items: center; flex-wrap: wrap; gap: .4rem 1.25rem; font-size: .875rem; font-weight: 500; }
        .am-public-nav a { display: inline-flex; align-items: center; min-height: 44px; }
        .am-public-nav a:hover, .am-landing-nav:hover { color: #1f6e3d; text-decoration: underline; text-underline-offset: 5px; }
        .am-public-nav a[aria-current] { color: #1f6e3d; font-weight: 600; }
        .am-public-login { border: 1px solid #d8e6dd; border-radius: .5rem; padding: 0 .85rem; }
        .am-public-footer { margin-top: auto; border-top: 1px solid #d8e6dd; background: white; }
        .am-public-footer .am-public-bar { padding-top: .75rem; padding-bottom: .75rem; }
        .am-public-footer p { font-size: .75rem; color: #64748b; line-height: 1.6; }
        .am-public-footer strong { color: #1f6e3d; }
        .am-public-footer .am-public-nav { font-size: .75rem; }
        .am-public-credit { max-width: 80rem; margin: auto; padding: 0 2rem 1rem; }
        .am-landing-main { max-width: none; padding: 0; }
        .am-landing-main > section:not(.am-landing-hero) { max-width: 76rem; margin: 0 auto; padding: 3.5rem 2rem; }
        .am-landing-main section[id] { scroll-margin-top: 7rem; }
        .am-landing-hero { position: relative; isolation: isolate; overflow: hidden; border: 0; border-radius: 0; box-shadow: none; padding: 4.5rem 2rem; background: #eef5f2; }
        /* Set --am-hero-image to url(...) when a suitable photograph is available. */
        .hero-background { position: absolute; inset: -2rem; z-index: -2; background-image: var(--am-hero-image, none); background-size: cover; background-position: center; filter: blur(12px); opacity: .24; pointer-events: none; }
        .hero-background-overlay { position: absolute; inset: 0; z-index: -1; background: linear-gradient(110deg, rgba(238,245,242,.96), rgba(238,245,242,.88)); pointer-events: none; }
        .hero-content { position: relative; max-width: 76rem; margin: auto; }
        .am-hero-copy { align-items: flex-start; text-align: left; }
        .am-hero-logo { width: 80px; height: 80px; object-fit: contain; margin-bottom: 1rem; }
        .am-hero-title { font-size: clamp(3rem, 6vw, 4.75rem); line-height: 1.08; letter-spacing: -.055em; color: #1f6e3d; font-weight: 700; }
        .am-hero-context { margin-top: 1rem; color: #475569; font-size: .875rem; font-weight: 500; }
        .am-hero-copy > p:not(.am-hero-context) { max-width: 33rem; color: #475569; line-height: 1.8; }
        .am-hero-actions { justify-content: flex-start; }
        .am-landing-primary { background: #1f6e3d; color: white; min-height: 44px; }
        .am-landing-primary:hover { background: #17572f; }
        .am-hero-panel { background: rgba(255,255,255,.65); border: 0; box-shadow: none; padding: 2rem; }
        .am-hero-panel > p:last-child { padding: 0; background: transparent; color: #475569; }
        .am-about-columns { gap: 3rem; }
        .am-about-columns article { padding: 0; border: 0; background: transparent; }
        .am-about-columns article + article { border-left: 2px solid #d8e6dd; padding-left: 2rem; }
        .am-landing-feature { border: 0; border-top: 2px solid #d8e6dd; border-radius: 0; background: transparent; padding: 1.5rem 0; }
        .am-landing-main > .am-participation { background: transparent; border: 0; border-radius: 0; border-top: 1px solid #d8e6dd; }
        .am-landing-main > .am-map-cta { border: 0; border-radius: 0; background: #e3eee7; }
        .am-geographic-info { border-left: 2px solid #b8d3c1; padding-left: 2rem; }
        .am-feature-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; margin-top: 2rem; }
        .am-public-gis { width: 100%; max-width: none; padding: 1rem 1.5rem; }
        .am-gis-shell { display: grid; grid-template-columns: minmax(240px, 1fr) minmax(0, 3fr); gap: 1rem; height: max(480px, calc(100vh - 190px)); height: max(480px, calc(100dvh - 190px)); }
        .am-gis-filter-rail { min-width: 0; overflow-y: auto; padding: 1rem; background: white; border-radius: .85rem; }
        .am-gis-map-area { position: relative; min-width: 0; min-height: 0; }
        .am-gis-map-area > .am-gis-map-panel { height: 100%; }
        .am-gis-filter-toggle > summary { cursor: pointer; padding: .65rem 0; font-size: .8rem; font-weight: 600; color: #1f6e3d; }
        .am-gis-filter-toggle .am-gis-controls { border: 0; margin: 0; padding: 0; }
        .am-gis-controls > label { display: block; font-size: .75rem; font-weight: 600; }
        .am-gis-filter-actions { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: 1rem; }
        .am-gis-intro { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: .35rem 1rem; margin-bottom: .75rem; }
        .am-gis-intro h1 { font-size: 1.125rem; font-weight: 700; }
        .am-gis-intro p { font-size: .75rem; color: #64748b; }
        .am-gis-controls { border-bottom: 1px solid #d8e6dd; padding-bottom: .75rem; margin-bottom: .75rem; }
        .am-gis-search-row { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .65rem; }
        .am-gis-search-row > label { flex: 1; min-width: min(100%, 16rem); font-size: .75rem; font-weight: 600; }
        .am-public-gis .gis-input { min-height: 40px; }
        .am-public-gis .gis-button { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; }
        .am-gis-filters { margin-top: .5rem; }
        .am-gis-filters summary { cursor: pointer; width: fit-content; padding: .5rem 0; color: #1f6e3d; font-size: .8rem; font-weight: 600; }
        .am-gis-filter-fields { display: grid; grid-template-columns: minmax(0,1fr); gap: .75rem; margin-top: .75rem; }
        .am-gis-filter-fields label { font-size: .75rem; font-weight: 600; min-width: 0; }
        .am-gis-count { margin: .5rem 0 .75rem; font-size: .75rem; color: #64748b; }
        .am-gis-map-panel { display: flex; flex-direction: column; min-width: 0; min-height: 0; overflow: hidden; border: 1px solid #d8e6dd; border-radius: .85rem; background: white; }
        .am-public-gis .am-gis-map-panel .gis-map { flex: 1; height: auto; min-height: 0; scroll-margin-top: 7rem; }
        .am-gis-map-panel > p { padding: .55rem .85rem; font-size: .75rem; line-height: 1.5; }
        .am-gis-locations { position: absolute; top: 6.5rem; right: .75rem; width: min(280px, calc(100% - 1.5rem)); z-index: 1; background: white; border: 1px solid #d8e6dd; border-radius: .75rem; }
        .am-gis-locations > summary { cursor: pointer; min-height: 44px; padding: .75rem 1rem; color: #1f6e3d; font-size: .875rem; font-weight: 600; }
        .am-gis-locations[open] > summary { border-bottom: 1px solid #d8e6dd; }
        .am-gis-locations h2 { padding: .85rem 1rem; border-bottom: 1px solid #d8e6dd; font-weight: 600; }
        .am-gis-location-list { max-height: min(42vh, 360px); max-height: min(42dvh, 360px); overflow-y: auto; overscroll-behavior-y: contain; }
        .am-gis-location-list article { padding: 1rem; }
        .am-gis-location-list h3 { color: #1f6e3d; font-size: .875rem; }
        .am-gis-location-list p { font-size: .75rem; line-height: 1.5; }
        .am-gis-pages { display: flex; gap: .75rem; margin-top: .75rem; font-size: .8rem; }
        @media (prefers-reduced-motion: no-preference) {
            html:has(.am-landing) { scroll-behavior: smooth; }
            .am-landing a, .am-landing-feature { transition: background-color 180ms ease, transform 180ms ease; }
            .am-landing-feature:hover { transform: translateY(-3px); }
            .hero-content { animation: am-public-enter 350ms ease-out; }
            .hero-background { animation: am-hero-drift 30s ease-in-out infinite alternate; }
            @keyframes am-public-enter { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
            @keyframes am-hero-drift { from { transform: scale(1); } to { transform: scale(1.04) translateX(1%); } }
        }
        @media (max-width: 1023px) {
            .am-landing-hero { padding-top: 3rem; padding-bottom: 3rem; }
            .am-gis-shell { grid-template-columns: minmax(220px, 1fr) minmax(0, 3fr); }
        }
        @media (max-width: 767px) {
            .am-public-bar { padding-left: 1rem; padding-right: 1rem; gap: .5rem; }
            .am-public-nav { gap: .25rem .9rem; }
            .am-public-header .am-public-nav { width: 100%; justify-content: space-between; }
            .am-landing-hero { padding: 2.5rem 1rem; }
            .am-landing-main > section:not(.am-landing-hero) { padding: 2.5rem 1rem; }
            .am-landing-main section[id] { scroll-margin-top: 9rem; }
            .am-about-columns article + article { border-left: 0; border-top: 1px solid #d8e6dd; padding: 1.5rem 0 0; }
            .am-hero-panel { padding: 1.5rem; }
            .am-public-gis { padding: 1rem; }
            .am-gis-shell { grid-template-columns: minmax(0,1fr); height: auto; }
            .am-gis-filter-rail { overflow: visible; padding: .85rem; }
            .am-gis-map-area > .am-gis-map-panel { height: auto; }
            .am-geographic-info { border-left: 0; border-top: 1px solid #b8d3c1; padding: 1.5rem 0 0; }
            .am-public-gis .am-gis-map-panel .gis-map { flex: none; height: 65vh; height: 65dvh; min-height: 360px; scroll-margin-top: 9rem; }
            .am-gis-locations { position: static; width: 100%; margin-top: .75rem; }
            .am-gis-filter-fields { grid-template-columns: repeat(2, minmax(0,1fr)); }
            .am-public-credit { padding-left: 1rem; padding-right: 1rem; }
        }
        @media (prefers-reduced-motion: reduce) {
            html:has(.am-landing) { scroll-behavior: auto; }
        }
        @media (pointer: coarse) {
            .am-public-gis :is(.gis-button, .gis-input), .am-gis-filters summary { min-height: 44px; }
        }
    </style>
    @else
    <style>
        html:has(.am-public), body:has(.am-public) { height: auto; min-height: 100%; overflow: visible; }
        html:has(.am-landing) { scroll-behavior: smooth; }
        .am-public { --am-green: #1f6e3d; --am-blue: #084873; --am-muted: #536878; --am-line: #dce7e4; --am-header-height: 72px; min-height: 100vh; min-height: 100dvh; display: flex; flex-direction: column; background: #fbfdfc; color: #193646; }
        .am-public *, .am-public *::before, .am-public *::after { box-sizing: border-box; }
        .am-public :is(a, button, input, select, summary, [tabindex]):focus-visible { outline: 3px solid #176c73; outline-offset: 3px; }
        .am-public [hidden] { display: none !important; }
        .am-public-header { position: sticky; top: 0; z-index: 1100; flex-shrink: 0; height: var(--am-header-height); background: #ffffffed; border-bottom: 1px solid var(--am-line); backdrop-filter: blur(14px); transition: background 250ms ease, box-shadow 250ms ease; }
        .am-public-header.is-scrolled { background: #fff; box-shadow: 0 4px 20px #163a520a; }
        .am-public-bar { max-width: 1320px; margin: auto; padding: 0 2.5rem; display: flex; align-items: center; justify-content: space-between; gap: 2rem; }
        .am-public-header .am-public-bar { height: 100%; }
        .am-public-brand { display: inline-flex; align-items: center; gap: .65rem; color: var(--am-blue); font-size: 1.45rem; font-weight: 700; letter-spacing: -.045em; flex-shrink: 0; }
        .am-public-brand img { width: 44px; height: 44px; object-fit: contain; }
        .am-public-nav { display: flex; align-items: center; gap: 1.75rem; font-size: .82rem; font-weight: 600; }
        .am-public-nav a { position: relative; display: inline-flex; align-items: center; min-height: 44px; color: var(--am-muted); transition: color 200ms ease; }
        .am-public-nav a:not(.am-public-login)::after { content: ''; position: absolute; bottom: 4px; left: 0; right: 0; height: 2px; background: var(--am-green); transform: scaleX(0); transform-origin: left; transition: transform 250ms ease; }
        .am-public-nav a:hover, .am-public-nav a[aria-current] { color: var(--am-green); }
        .am-public-nav a:hover::after, .am-public-nav a[aria-current]::after { transform: scaleX(1); }
        .am-public-nav .am-public-login { min-height: 40px; padding: 0 1.2rem; border: 1px solid #b9cfc4; border-radius: 8px; color: var(--am-green); background: #f6faf7; }
        .am-public-login:hover { background: #e9f3ed; }
        .am-nav-toggle { display: none; }
        .am-landing-main { padding: 0; max-width: none; }
        .am-landing-main section[id] { scroll-margin-top: calc(var(--am-header-height) + 24px); }
        .am-landing-main > section:not(.am-landing-hero) { max-width: 1240px; margin: auto; padding: 6rem 2.5rem; }
        .am-landing-main h2 { color: var(--am-blue); line-height: 1.2; letter-spacing: -.04em; font-size: clamp(1.8rem, 3vw, 2.6rem); }
        .am-landing-main p, .am-landing-main li { line-height: 1.8; }
        .am-landing-hero { position: relative; isolation: isolate; overflow: hidden; min-height: 84vh; min-height: 84dvh; padding: 5rem 2.5rem; display: flex; align-items: center; background: #edf5f2; }
        .hero-background { position: absolute; inset: 0; z-index: -2; overflow: hidden; pointer-events: none; background: radial-gradient(ellipse at 85% 30%, #b9dce280, transparent 55%), radial-gradient(ellipse at 10% 90%, #c8dec880, transparent 55%), linear-gradient(120deg, #f5f9f3, #e9f3f4); }
        /* Supply an image through --am-hero-image or an image element in this layer. */
        .hero-background-media { position: absolute; inset: -3%; width: 106%; height: 106%; object-fit: cover; background-image: var(--am-hero-image, none); background-size: cover; background-position: center; filter: blur(7px); opacity: .24; animation: am-hero-drift 32s ease-in-out infinite alternate; }
        .hero-background-overlay { position: absolute; inset: 0; z-index: -1; pointer-events: none; background: linear-gradient(100deg, #f5f9f3b8, #edf5f23d); }
        .hero-content { position: relative; width: 100%; max-width: 1240px; margin: auto; display: grid; grid-template-columns: 1fr 1.05fr; align-items: center; gap: 4rem; }
        .am-hero-copy { align-items: flex-start; }
        .am-hero-context { color: var(--am-green); font-size: .7rem; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; margin-bottom: 1.6rem; }
        .am-hero-title { font-size: clamp(3.6rem, 7vw, 6.5rem); line-height: 1.02; letter-spacing: -.065em; color: var(--am-blue); font-weight: 700; }
        .am-hero-subtitle { margin-top: 1.25rem; max-width: 29rem; font-size: clamp(1.3rem, 2.3vw, 1.85rem); font-weight: 600; line-height: 1.35; letter-spacing: -.025em; color: #24535b; }
        .am-hero-copy > p:not(.am-hero-context) { max-width: 31rem; color: var(--am-muted); }
        .am-hero-actions { display: flex; flex-wrap: wrap; gap: .75rem; }
        .am-landing-primary { min-height: 48px; background: var(--am-green); color: white; box-shadow: 0 5px 14px #1f6e3d18; transition: background 200ms ease, transform 200ms ease; }
        .am-landing-primary:hover { background: #185832; transform: translateY(-2px); }
        .am-cta-arrow { display: inline-block; margin-left: .65rem; transition: transform 200ms ease; }
        a:hover .am-cta-arrow { transform: translateX(4px); }
        .am-landing-nav { color: var(--am-muted); }
        .am-landing-nav:hover { color: var(--am-green); text-decoration: underline; text-underline-offset: 4px; }
        .am-hero-panel { position: relative; min-width: 0; padding: .65rem; border: 1px solid #ffffffdf; border-radius: 18px; background: #ffffff70; box-shadow: 0 24px 60px #08487312; transform: rotate(1deg); }
        .am-map-presentation { position: relative; isolation: isolate; overflow: hidden; min-height: 400px; border: 1px solid #ccdedc; border-radius: 12px; background: radial-gradient(ellipse at 75% 35%, #b8d9df, transparent 60%), linear-gradient(140deg, #eef3e8, #dcebea 70%); display: flex; flex-direction: column; justify-content: space-between; padding: 1.5rem; }
        .am-map-presentation::before { content: ''; position: absolute; inset: 0; z-index: -1; background-image: linear-gradient(#ffffff50 1px, transparent 1px), linear-gradient(90deg, #ffffff50 1px, transparent 1px); background-size: 42px 42px; mask-image: linear-gradient(140deg, black, #0004); }
        .am-map-presentation::after { content: ''; position: absolute; width: 390px; height: 390px; border: 1px solid #176c7326; border-radius: 50%; left: 50%; top: 50%; transform: translate(-50%, -50%); box-shadow: 0 0 0 38px #176c7306, 0 0 0 76px #176c7305; z-index: -1; }
        .am-map-presentation-label { align-self: flex-start; padding: .45rem .7rem; background: #ffffffe6; border: 1px solid #dce7e4; border-radius: 6px; color: var(--am-blue); font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .12em; }
        .am-map-symbol { align-self: center; width: 118px; height: 118px; color: #176c73; padding: 1.8rem; border: 1px solid #ffffffc9; border-radius: 24px; background: #ffffff7d; box-shadow: 0 12px 35px #0848730d; }
        .am-map-presentation-caption { padding: 1rem 1.15rem; border: 1px solid #ffffffd9; border-radius: 9px; background: #ffffffed; }
        .am-map-presentation-caption h2, .am-map-presentation-caption h3 { font-size: 1.1rem; font-weight: 700; color: var(--am-blue); letter-spacing: -.025em; }
        .am-map-presentation-caption p { margin-top: .25rem; font-size: .75rem; color: var(--am-muted); }
        .am-hero-panel > p { padding: .75rem .75rem .3rem; color: var(--am-muted); font-size: .7rem; }
        .am-about-columns { gap: 4rem; margin-top: 2.5rem; }
        .am-about-columns article { padding: 0; border: 0; background: transparent; }
        .am-about-columns article + article { padding-left: 2.5rem; border-left: 1px solid var(--am-line); }
        .am-landing-main > .am-participation { max-width: none; background: #eef5f2; border-top: 1px solid #e0ebe4; border-bottom: 1px solid #e0ebe4; }
        .am-participation > div { max-width: 1160px; margin: auto; }
        .am-participation-note { margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid #cadfd4; color: var(--am-blue); font-size: .8rem; }
        .am-geographic-info { min-height: 330px; }
        .am-landing-feature { height: 100%; padding: 1.75rem; border: 1px solid var(--am-line); border-radius: 12px; background: white; box-shadow: 0 4px 16px #163a5204; transition: transform 250ms ease, box-shadow 250ms ease, border-color 250ms ease; }
        .am-landing-feature:hover { transform: translateY(-4px); box-shadow: 0 12px 25px #163a520b; border-color: #a8c8bb; }
        .am-feature-icon { width: 44px; height: 44px; padding: 11px; margin-bottom: 1.4rem; border-radius: 10px; color: #176c73; background: #edf5f2; transition: transform 250ms ease; }
        .am-landing-feature:hover .am-feature-icon { transform: scale(1.06); }
        .am-feature-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 1rem; margin-top: 2rem; }
        .am-public-footer { margin-top: auto; border-top: 1px solid var(--am-line); background: #f0f5f3; }
        .am-footer-main { max-width: 1240px; margin: auto; padding: 3rem 2.5rem 2rem; display: flex; justify-content: space-between; gap: 2rem; }
        .am-footer-main p { margin-top: .8rem; font-size: .8rem; line-height: 1.8; color: var(--am-muted); }
        .am-footer-context { text-align: right; }
        .am-footer-context strong { color: var(--am-blue); }
        .am-public-footer > .am-public-bar { padding-top: 1rem; padding-bottom: 1rem; border-top: 1px solid var(--am-line); }
        .am-public-credit { font-size: .65rem; color: var(--am-muted); line-height: 1.8; }
        .am-public-footer .am-public-nav { font-size: .7rem; gap: 1rem; }
        .am-footer-compact { min-height: 30px; padding: .45rem 1rem; font-size: .65rem; color: var(--am-muted); text-align: center; }
        .am-public:has(.am-public-gis) { height: 100vh; height: 100dvh; min-height: 0; overflow: hidden; }
        .am-public-gis { flex: 1; min-height: 0; width: 100%; padding: 0; position: relative; background: #e8eeeb; }
        .am-gis-shell { position: relative; display: flex; height: 100%; overflow: hidden; }
        .am-gis-filter-rail { flex: 0 0 clamp(320px, 20vw, 380px); width: clamp(320px, 20vw, 380px); min-width: 0; z-index: 5; padding: 1.5rem; overflow-y: auto; overscroll-behavior: contain; background: #fffffff7; border-right: 1px solid var(--am-line); box-shadow: 5px 0 20px #163a5208; transition: margin-left 300ms ease, visibility 300ms; }
        .am-gis-shell.is-filters-collapsed .am-gis-filter-rail { margin-left: calc(-1 * clamp(320px, 20vw, 380px)); visibility: hidden; }
        .am-gis-intro { padding-bottom: 1.25rem; margin-bottom: 1.25rem; border-bottom: 1px solid var(--am-line); }
        .am-gis-intro h1 { max-width: 15rem; font-size: 1.4rem; font-weight: 700; line-height: 1.3; color: var(--am-blue); letter-spacing: -.035em; }
        .am-gis-intro p { margin-top: .7rem; font-size: .75rem; line-height: 1.7; color: var(--am-muted); }
        .am-gis-filter-heading { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin-bottom: 1.1rem; }
        .am-gis-filter-heading h2 { font-size: .9rem; font-weight: 700; }
        .am-gis-filter-heading span { color: var(--am-green); background: #edf5f2; border-radius: 5px; padding: .25rem .5rem; font-size: .65rem; }
        .am-gis-controls label { display: block; font-size: .73rem; font-weight: 600; color: #34505b; }
        .am-gis-filter-fields { display: grid; gap: 1rem; margin-top: 1rem; }
        .am-public-gis .gis-input { display: block; width: 100%; margin-top: .45rem; min-height: 44px; padding: .65rem .75rem; border: 1px solid #cddbd6; border-radius: 7px; background: #fbfdfc; color: #193646; font-size: .8rem; font-weight: 400; transition: border-color 200ms ease, background 200ms ease; }
        .am-public-gis .gis-input:focus { border-color: #176c73; background: white; }
        .am-gis-filter-actions { display: grid; gap: .6rem; margin-top: 1.5rem; }
        .am-public-gis .gis-button { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: .55rem .8rem; border: 1px solid #cddbd6; border-radius: 7px; background: white; color: #34505b; font-size: .75rem; font-weight: 600; transition: background 200ms ease; }
        .am-public-gis .gis-button:hover { background: #edf5f2; }
        .am-public-gis .gis-save-button { background: var(--am-green); border-color: var(--am-green); color: white; }
        .am-public-gis .gis-save-button:hover { background: #185832; }
        .am-gis-map-area { flex: 1; min-width: 0; position: relative; height: 100%; }
        .am-gis-map-panel { position: absolute; inset: 0; }
        .am-public-gis .am-gis-map-panel .gis-map { width: 100%; height: 100%; min-height: 0; z-index: 0; }
        .am-gis-filter-button { position: absolute; left: 12px; top: 14px; z-index: 4; box-shadow: 0 4px 14px #163a5214; }
        .am-gis-filter-button span { margin-right: .4rem; }
        .am-public-gis .leaflet-top.leaflet-left { top: 68px; }
        .am-public-gis .leaflet-top.leaflet-right { right: 8px; top: 8px; }
        .am-gis-status { position: absolute; z-index: 2; left: 14px; bottom: 30px; max-width: min(370px, calc(100% - 28px)); padding: .8rem 1rem; border: 1px solid #dce7e4; border-radius: 9px; background: #fffffff2; box-shadow: 0 4px 16px #163a520b; pointer-events: none; }
        .am-gis-status p { font-size: .67rem; line-height: 1.6; color: var(--am-muted); }
        .am-gis-status [data-viewer-status] { color: var(--am-blue); font-weight: 600; margin-bottom: .3rem; }
        .am-gis-map-help { margin-top: .35rem; }
        .am-gis-locations { position: absolute; top: 14px; right: 68px; z-index: 3; width: min(330px, calc(100% - 200px)); border: 1px solid var(--am-line); border-radius: 10px; background: #fffffff7; box-shadow: 0 8px 25px #163a5210; overflow: hidden; }
        .am-gis-locations > summary { min-height: 44px; padding: .8rem 1rem; cursor: pointer; color: var(--am-blue); font-size: .8rem; font-weight: 700; }
        .am-gis-locations[open] > summary { border-bottom: 1px solid var(--am-line); }
        .am-gis-location-list { max-height: min(55vh, 480px); max-height: min(55dvh, 480px); overflow-y: auto; overscroll-behavior: contain; }
        .am-gis-location-list article { padding: 1rem 1.15rem; border-bottom: 1px solid #e6eeea; overflow-wrap: anywhere; }
        .am-gis-location-list h3 { font-size: .9rem; color: var(--am-blue); }
        .am-gis-location-list p { font-size: .72rem; line-height: 1.65; color: var(--am-muted); }
        .am-gis-pages { padding: .65rem 1rem; display: flex; gap: .5rem; border-top: 1px solid var(--am-line); }
        .am-gis-pages:empty { display: none; }
        .am-public-gis .leaflet-popup-content-wrapper { border-radius: 10px; box-shadow: 0 6px 25px #163a5226; }
        .am-public-gis .leaflet-popup-content { font-family: inherit; line-height: 1.7; color: var(--am-blue); }
        .am-public-gis .gis-popup button { min-height: 44px; }
        .am-gis-unavailable { position: absolute; inset: 5rem 1rem auto; padding: 1.5rem; border: 1px solid #e5c48a; border-radius: 10px; background: #fffdf8; }
        .am-public-gis > noscript p { position: absolute; bottom: 0; left: 0; right: 0; padding: .4rem 1rem; background: #fff9df; font-size: .7rem; z-index: 6; }
        @keyframes am-enter { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes am-panel-enter { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes am-hero-drift { from { transform: scale(1); } to { transform: scale(1.05) translateX(1%); } }
        @media (prefers-reduced-motion: no-preference) {
            .am-hero-context, .am-hero-title { animation: am-enter 600ms ease both; }
            .am-hero-subtitle, .am-hero-copy > p:not(.am-hero-context) { animation: am-enter 600ms 120ms ease both; }
            .am-hero-actions { animation: am-enter 600ms 230ms ease both; }
            .am-hero-panel { animation: am-panel-enter 750ms 180ms ease backwards; }
            .am-reveal-pending { opacity: 0; transform: translateY(20px); }
            .am-reveal-ready { transition: opacity 600ms ease var(--am-reveal-delay, 0ms), transform 600ms ease var(--am-reveal-delay, 0ms); }
            .am-gis-locations[open] .am-gis-location-list { animation: am-panel-enter 300ms ease; }
            .am-gis-status { animation: am-enter 450ms ease; }
        }
        @media (min-width: 1800px) { .am-gis-filter-rail { flex-basis: 18vw; width: 18vw; } .am-gis-shell.is-filters-collapsed .am-gis-filter-rail { margin-left: -18vw; } }
        @media (max-width: 1023px) {
            .hero-content { gap: 2rem; }
            .am-map-presentation { min-height: 340px; }
            .am-landing-hero { min-height: 78vh; padding: 4rem 2rem; }
            .am-gis-filter-rail { flex-basis: 290px; width: 290px; padding: 1.25rem; }
            .am-gis-shell.is-filters-collapsed .am-gis-filter-rail { margin-left: -290px; }
            .am-gis-locations { width: min(300px, calc(100% - 200px)); }
        }
        @media (max-width: 767px) {
            .am-public { --am-header-height: 64px; }
            .am-public-bar { padding: 0 1rem; gap: 1rem; }
            .am-public-brand { font-size: 1.25rem; }
            .am-public-brand img { width: 38px; height: 38px; }
            .am-nav-toggle { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 .85rem; border: 1px solid var(--am-line); border-radius: 7px; color: var(--am-blue); font-size: .8rem; font-weight: 600; background: white; }
            .am-public-header .am-public-nav { position: absolute; top: 100%; left: 0; right: 0; padding: .75rem 1rem; gap: .5rem; justify-content: space-between; background: white; border-bottom: 1px solid var(--am-line); box-shadow: 0 8px 16px #163a520a; }
            .am-public-header .am-public-nav a { font-size: .75rem; }
            .am-public-header .am-public-login { padding: 0 .7rem; }
            .hero-content { grid-template-columns: 1fr; gap: 2.5rem; }
            .am-landing-hero { padding: 3.5rem 1.25rem; min-height: auto; }
            .am-hero-context { font-size: .62rem; }
            .am-hero-title { font-size: clamp(3.5rem, 14vw, 5rem); }
            .am-hero-actions { width: 100%; }
            .am-hero-actions a { flex: 1; }
            .am-hero-panel { transform: none; }
            .am-map-presentation { min-height: 320px; padding: 1rem; }
            .am-landing-main > section:not(.am-landing-hero) { padding: 3.5rem 1.25rem; }
            .am-about-columns { gap: 2rem; }
            .am-about-columns article + article { padding: 1.5rem 0 0; border-left: 0; border-top: 1px solid var(--am-line); }
            .am-footer-main { padding: 2rem 1.25rem; flex-direction: column; }
            .am-footer-context { text-align: left; }
            .am-public-footer > .am-public-bar { flex-direction: column; align-items: flex-start; padding: 1rem 1.25rem; }
            .am-gis-filter-rail { position: absolute; inset: 0 auto 0 0; flex-basis: auto; width: min(340px, calc(100% - 24px)); padding: 1.25rem; padding-top: 4.5rem; border-radius: 0 12px 12px 0; transition: transform 300ms ease, visibility 300ms; }
            .am-gis-shell.is-filters-collapsed .am-gis-filter-rail { margin-left: 0; transform: translateX(-100%); }
            .am-gis-filter-button { position: fixed; top: calc(var(--am-header-height) + 14px); left: 12px; z-index: 7; }
            .am-gis-locations { width: min(340px, calc(100% - 24px)); right: 12px; top: 72px; }
            .am-gis-locations:not([open]) { width: auto; }
            .am-gis-location-list { max-height: calc(100dvh - 290px); }
            .am-gis-status { bottom: 34px; max-width: min(310px, calc(100% - 75px)); padding: .6rem .75rem; }
            .am-gis-map-help { display: none; }
            .am-footer-compact { font-size: .58rem; }
        }
        @media (prefers-reduced-motion: reduce) {
            html:has(.am-landing) { scroll-behavior: auto; }
            .am-public *, .am-public *::before, .am-public *::after { animation: none !important; transition: none !important; }
            .am-reveal-pending { opacity: 1; transform: none; }
            .am-landing-primary:hover, .am-landing-feature:hover, .am-landing-feature:hover .am-feature-icon { transform: none; }
        }
    </style>
    @endif
    <div class="am-public">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:block focus:bg-white focus:p-4">Skip to content</a>
        <header class="am-public-header">
            <div class="am-public-bar">
                <a href="{{ route('home') }}" class="am-public-brand" aria-label="AssocMap home">
                    <img src="{{ asset('assocmap-logo.png') }}" width="44" height="44" alt="" />
                    <span>AssocMap</span>
                </a>
                @unless($internal)
                    <button type="button" class="am-nav-toggle" data-public-nav-toggle aria-controls="public-navigation" aria-expanded="true" hidden>Menu</button>
                @endunless
                <nav id="public-navigation" class="am-public-nav" aria-label="Main navigation">
                    <a href="{{ route('home') }}" @if($current === 'home') aria-current="page" @endif>Home</a>
                    <a href="{{ $current === 'home' ? '#learn-more' : route('home').'#learn-more' }}">Learn More</a>
                    <a href="{{ route('gis.public') }}" @if($current === 'map' && !$internal) aria-current="page" @endif>GIS Map</a>
                    <a class="am-public-login" href="{{ route($internal ? 'gis.public' : 'login') }}">{{ $internal ? 'Public map' : 'Login' }}</a>
                </nav>
            </div>
        </header>
        {{ $slot }}
        <footer class="am-public-footer">
            @if(!$internal && $current === 'map')
                <p class="am-footer-compact">AssocMap &middot; BFAR Region VII / SAAD Phase II &middot; Cebu Province</p>
            @elseif(!$internal)
                <div class="am-footer-main">
                    <div>
                        <a href="{{ route('home') }}" class="am-public-brand"><img src="{{ asset('assocmap-logo.png') }}" width="44" height="44" alt="" /><span>AssocMap</span></a>
                        <p>GIS-Based Program Monitoring System<br />for Community Livelihood Associations</p>
                    </div>
                    <div class="am-footer-context"><strong>BFAR Region VII</strong><p>Special Area for Agricultural Development<br />SAAD Phase II &middot; Cebu Province</p></div>
                </div>
                <div class="am-public-bar">
                    <p class="am-public-credit">&copy; {{ date('Y') }} DA-BFAR Region VII.<br />Developed by Group Koinonia &middot; Cebu Technological University.</p>
                    <nav class="am-public-nav" aria-label="Footer navigation">
                        <a href="{{ route('home') }}">Home</a><a href="#learn-more">Learn More</a><a href="{{ route('gis.public') }}">GIS Map</a><a href="{{ route('login') }}">Login</a>
                    </nav>
                </div>
            @else
            <div class="am-public-bar">
                <p><strong>AssocMap</strong> &middot; BFAR Region VII / SAAD Phase II<br />Cebu Province</p>
                <nav class="am-public-nav" aria-label="Footer navigation">
                    <a href="{{ route('home') }}">Home</a>
                    <a href="{{ route('gis.public') }}">GIS Map</a>
                    <a href="{{ $current === 'home' ? '#learn-more' : route('home').'#learn-more' }}">Learn More</a>
                    <a href="{{ route('login') }}">Login</a>
                </nav>
            </div>
            @if($current === 'home')
                <p class="am-public-credit">&copy; {{ date('Y') }} Department of Agriculture &mdash; Bureau of Fisheries and Aquatic Resources, Region VII.<br />Developed by Group Koinonia &middot; Cebu Technological University.</p>
            @endif
            @endif
        </footer>
    </div>
    @unless($internal)
        <script>
            (() => {
                const header = document.querySelector('.am-public-header');
                const nav = document.getElementById('public-navigation');
                const toggle = document.querySelector('[data-public-nav-toggle]');
                const mobile = window.matchMedia('(max-width: 767px)');
                const syncNavigation = () => {
                    toggle.hidden = !mobile.matches;
                    nav.hidden = mobile.matches;
                    toggle.setAttribute('aria-expanded', String(!nav.hidden));
                };
                syncNavigation();
                mobile.addEventListener('change', syncNavigation);
                toggle.addEventListener('click', () => {
                    nav.hidden = !nav.hidden;
                    toggle.setAttribute('aria-expanded', String(!nav.hidden));
                });
                const closeNavigation = () => {
                    if (!mobile.matches) return;
                    nav.hidden = true;
                    toggle.setAttribute('aria-expanded', 'false');
                };
                nav.addEventListener('click', event => { if (event.target.closest('a')) closeNavigation(); });
                header.addEventListener('keydown', event => {
                    if (event.key === 'Escape' && mobile.matches && !nav.hidden) { closeNavigation(); toggle.focus(); }
                });
                const syncHeader = () => header.classList.toggle('is-scrolled', window.scrollY > 12);
                syncHeader();
                window.addEventListener('scroll', syncHeader, { passive: true });
                const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
                if (!motion.matches && 'IntersectionObserver' in window) {
                    const observer = new IntersectionObserver(entries => {
                        entries.forEach(entry => {
                            if (!entry.isIntersecting) return;
                            entry.target.classList.remove('am-reveal-pending');
                            observer.unobserve(entry.target);
                        });
                    }, { threshold: .08 });
                    document.querySelectorAll('.am-landing-main > section:not(.am-landing-hero), .am-landing-feature').forEach((section, index) => {
                        section.style.setProperty('--am-reveal-delay', section.matches('.am-landing-feature') ? `${index % 3 * 90}ms` : '0ms');
                        section.classList.add('am-reveal-ready', 'am-reveal-pending');
                        observer.observe(section);
                    });
                    motion.addEventListener('change', event => {
                        if (event.matches) { observer.disconnect(); document.querySelectorAll('.am-reveal-pending').forEach(section => section.classList.remove('am-reveal-pending')); }
                    });
                }
            })();
        </script>
    @endunless
</x-app-layout>
