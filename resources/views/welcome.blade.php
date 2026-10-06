{{-- Public landing page: GET / --}}
<x-public-layout title="Welcome" current="home">

    <div class="am-landing">

        <main id="main-content" class="am-landing-main" tabindex="-1">
            <section class="am-landing-hero" aria-label="Welcome to AssocMAP">
                <div class="hero-background" aria-hidden="true"><div class="hero-background-media"></div></div>
                <div class="hero-background-overlay" aria-hidden="true"></div>
                <div class="hero-content grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
                    <div class="am-hero-copy flex flex-col">
                        <p class="am-hero-context">DA-BFAR Region VII &middot; SAAD Phase II</p>
                        <h1 class="am-hero-title">AssocMap</h1>
                        <h2 class="am-hero-subtitle">A GIS-based program monitoring system</h2>
                        <p class="mt-6 max-w-lg text-sm leading-relaxed text-assocmap-secondary sm:text-base">
                            Connecting community livelihood associations, SAAD-supported activities,
                            and geographic information in Cebu Province under BFAR Region VII.
                        </p>
                        <div class="am-hero-actions mt-8 flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                            <a href="{{ route('gis.public') }}" class="am-landing-primary inline-flex items-center justify-center gap-2 rounded-lg px-6 py-3 text-sm font-semibold">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z" /><path d="M9 3v15M15 6v15" />
                                </svg>
                                Explore GIS Map <span class="am-cta-arrow" aria-hidden="true">&rarr;</span>
                            </a>
                            <a href="#learn-more" class="inline-flex items-center justify-center rounded-lg border border-assocmap-border bg-white px-6 py-3 text-sm font-semibold text-assocmap-primary hover:bg-assocmap-bg">Learn More</a>
                        </div>
                        <a href="{{ route('login') }}" class="am-landing-nav mt-5 inline-flex items-center gap-2 px-3 py-2 text-sm text-assocmap-secondary">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                <rect x="3" y="11" width="18" height="10" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" />
                            </svg>
                            Login
                        </a>
                    </div>
                    <aside class="am-hero-panel rounded-2xl" aria-labelledby="coverage-heading">
                        <div class="am-map-presentation">
                            <span class="am-map-presentation-label">Cebu Province &middot; Public GIS</span>
                            <svg class="am-map-symbol" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z" /><path d="M9 3v15M15 6v15" />
                            </svg>
                            <div class="am-map-presentation-caption">
                                <h2 id="coverage-heading">Discover program coverage</h2>
                                <p>Search associations. Explore locations. Understand geographic context.</p>
                            </div>
                        </div>
                        <p>Geographic presentation area &middot; Open the public map to view published locations.</p>
                    </aside>
                </div>
            </section>

            <section id="learn-more" aria-labelledby="about-heading">
                <p class="text-xs font-semibold uppercase tracking-widest text-assocmap-primary">Learn More</p>
                <h2 id="about-heading" class="mt-3 text-2xl font-bold tracking-tight sm:text-3xl">What is AssocMap?</h2>
                <div class="am-about-columns mt-6 grid gap-6 md:grid-cols-2">
                    <article class="rounded-2xl border border-assocmap-border bg-white p-6 sm:p-8">
                        <h3 class="text-lg font-semibold text-assocmap-primary">Program information in one place</h3>
                        <p class="mt-3 text-sm leading-relaxed text-assocmap-secondary">AssocMAP brings association information, livelihood projects, training activities, monitoring records, and geographic data into one system. Authorized users manage program records, while visitors can explore published locations through the public GIS map.</p>
                    </article>
                    <article class="rounded-2xl border border-assocmap-border bg-white p-6 sm:p-8">
                        <h3 class="text-lg font-semibold text-assocmap-primary">The SAAD Phase II context</h3>
                        <p class="mt-3 text-sm leading-relaxed text-assocmap-secondary">SAAD stands for Special Area for Agricultural Development. AssocMAP supports the monitoring and management of community livelihood associations under BFAR Region VII's SAAD Phase II program in Cebu Province.</p>
                    </article>
                </div>
            </section>

            <section id="how-to-join" class="am-participation" aria-labelledby="participation-heading">
                <div class="grid gap-4 md:grid-cols-2 md:gap-10">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-assocmap-primary">Community participation</p>
                        <h2 id="participation-heading" class="mt-3 text-2xl font-bold tracking-tight">How to Join / Learn More</h2>
                    </div>
                    <div>
                        <p class="text-sm leading-relaxed text-assocmap-secondary">Interested fisherfolk groups and community associations may coordinate with the appropriate BFAR/SAAD office to learn about eligibility, registration, and available program support.</p>
                        <p class="am-participation-note">Learn about the program and coordinate with the appropriate office for participation guidance.</p>
                    </div>
                </div>
            </section>

            <section id="geographic-context" class="am-map-cta" aria-labelledby="map-heading">
                <div class="grid items-center gap-8 md:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-widest text-assocmap-primary">GIS / Geographic Context</p>
                        <h2 id="map-heading" class="mt-3 text-2xl font-bold tracking-tight sm:text-3xl">See the geographic context of livelihood programs</h2>
                        <p class="mt-3 text-sm leading-relaxed text-assocmap-secondary">Explore published association and project locations across Cebu Province. The public map connects program information with barangays and municipalities, helping visitors understand where activities are located.</p>
                        <a href="{{ route('gis.public') }}" class="am-landing-primary mt-6 inline-flex items-center justify-center rounded-lg px-6 py-3 text-sm font-semibold">View Public GIS Map <span class="am-cta-arrow" aria-hidden="true">&rarr;</span></a>
                    </div>
                    <div class="am-geographic-info am-map-presentation">
                        <span class="am-map-presentation-label">Location &middot; Program &middot; Community</span>
                        <svg class="am-map-symbol" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z" /><circle cx="12" cy="10" r="3" /></svg>
                        <div class="am-map-presentation-caption"><h3>From location to program context</h3><p>Filter by municipality, barangay, component, or commodity. Published locations only; no account needed.</p></div>
                    </div>
                </div>
            </section>

            <section id="features" aria-labelledby="features-heading">
                <p class="text-xs font-semibold uppercase tracking-widest text-assocmap-primary">Features / Monitoring</p>
                <h2 id="features-heading" class="mt-3 text-2xl font-bold tracking-tight sm:text-3xl">Information that supports program monitoring.</h2>
                <p class="mt-3 max-w-2xl text-sm leading-relaxed text-assocmap-secondary">Authorized users work with program records according to their role and association access. Visitors can explore published geographic information.</p>
                <div class="mt-6 grid gap-5 md:grid-cols-3">
                    <article class="am-landing-feature">
                        <svg class="am-feature-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z" /><path d="M9 3v15M15 6v15" /></svg>
                        <h3 class="text-lg font-semibold">Associations and program activities</h3>
                        <p class="mt-3 text-sm leading-relaxed text-assocmap-secondary">Keep association information, livelihood project records, and training activities organized within the system.</p>
                    </article>
                    <article class="am-landing-feature">
                        <svg class="am-feature-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><rect x="3" y="11" width="18" height="10" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
                        <h3 class="text-lg font-semibold">Monitoring and reports</h3>
                        <p class="mt-3 text-sm leading-relaxed text-assocmap-secondary">Record production, income, and materials monitoring information. Reporting tools support users with the appropriate permissions.</p>
                    </article>
                    <article class="am-landing-feature">
                        <svg class="am-feature-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z" /><circle cx="12" cy="10" r="3" /></svg>
                        <h3 class="text-lg font-semibold">Public GIS mapping</h3>
                        <p class="mt-3 text-sm leading-relaxed text-assocmap-secondary">View published association locations, program components, and available project and commodity details on the map.</p>
                    </article>
                </div>
                <div class="am-feature-actions">
                    <a href="{{ route('gis.public') }}" class="am-landing-primary inline-flex items-center justify-center rounded-lg px-6 py-3 text-sm font-semibold">Explore GIS Map</a>
                    <a href="{{ route('login') }}" class="am-landing-nav px-3 py-3 text-sm text-assocmap-secondary">Login</a>
                </div>
            </section>
        </main>

    </div>
</x-public-layout>
