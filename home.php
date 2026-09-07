<?php
$home = __DIR__ . '/index.html';
$html = @file_get_contents($home);
if ($html === false) {
    http_response_code(500);
    echo 'Unable to load homepage.';
    exit;
}
$block = <<<'HTML'
    <section id="free-list-property" class="py-16 sm:py-20 bg-gradient-to-b from-emerald-950/20 via-dark-950 to-dark-900 border-y border-emerald-500/10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="rounded-3xl border border-emerald-500/30 bg-dark-900/90 p-6 sm:p-10 lg:p-12 shadow-2xl shadow-emerald-500/10 overflow-hidden relative">
                <div class="absolute -top-24 -right-24 w-72 h-72 rounded-full bg-emerald-500/10 blur-3xl pointer-events-none"></div>
                <div class="relative grid lg:grid-cols-[1.35fr_.65fr] gap-8 items-center">
                    <div>
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/25 text-emerald-400 text-xs font-extrabold uppercase tracking-wider"><i data-lucide="home" class="w-4 h-4"></i> Direct Property Owners</span>
                        <h2 class="mt-4 text-3xl sm:text-4xl lg:text-5xl font-extrabold text-white tracking-tight"><span class="text-emerald-400">FREE</span> List Your Property</h2>
                        <p class="mt-4 text-slate-300 text-sm sm:text-base max-w-2xl leading-relaxed">Owner account create karke apni property sale ya rent ke liye submit karein. Leads India team verification ke baad approved property marketplace par live hogi.</p>
                        <div class="mt-6 flex flex-wrap gap-3 text-xs sm:text-sm text-slate-300"><span class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-white/5 border border-white/10"><i data-lucide="badge-check" class="w-4 h-4 text-emerald-400"></i> Free Owner Listing</span><span class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-white/5 border border-white/10"><i data-lucide="shield-check" class="w-4 h-4 text-emerald-400"></i> Verification Before Live</span><span class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-white/5 border border-white/10"><i data-lucide="layout-dashboard" class="w-4 h-4 text-emerald-400"></i> My Properties Dashboard</span></div>
                    </div>
                    <div class="rounded-2xl bg-dark-950 border border-white/10 p-5 sm:p-6">
                        <div class="text-sm font-bold text-white">Property Owner?</div>
                        <p class="text-xs text-slate-400 mt-1">Create your account and start onboarding your property.</p>
                        <a href="/owner/register.php" class="mt-5 w-full flex items-center justify-center gap-2 py-3.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-black font-extrabold text-sm shadow-lg shadow-emerald-500/20 transition"><i data-lucide="plus-circle" class="w-4 h-4"></i> FREE List Your Property</a>
                        <a href="/owner/login.php" class="mt-3 w-full flex items-center justify-center gap-2 py-3 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-white font-bold text-sm transition">Already have an account? Login</a>
                        <p class="text-[11px] text-slate-500 text-center mt-3">No property goes live without Leads India verification.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
HTML;
$marker = '    <!-- SECTION 1: TURNKEY SETUPS (HIGH-TICKET DFY - PRIMARY CASHFLOW ENGINE) -->';
$html = strpos($html, $marker) !== false ? str_replace($marker, $block . "\n\n" . $marker, $html) : str_replace('</body>', $block . "\n</body>", $html);
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
echo $html;
