const I18N = {
    en: {
        search_placeholder: "Search movies, series, anime...",
        loading: "Loading...",
        search_results: "Search Results",
        trending: "Trending",
        all_sources: "All Sources",
        watch_now: "Watch Now",
        seasons: "Seasons",
        episodes: "Episodes",
        server: "Server",
        no_results: "No results found",
        try_different: "Try a different search or source",
        paste_url: "Paste a URL",
        paste_url_desc: "Enter a streaming page URL to scrape",
        scrape: "Scrape",
        source: "Source",
        year: "Year",
        type_movie: "Movie",
        type_series: "Series",
        type_anime: "Anime",
        back: "Back",
        languages: "Languages",
        quality_select: "Quality",
        episode: "Episode",
        season: "Season",
        home: "Home",
        settings: "Settings",
        clear_cache: "Clear Cache",
    },
    ar: {
        search_placeholder: "ابحث عن أفلام، مسلسلات، أنمي...",
        loading: "جاري التحميل...",
        search_results: "نتائج البحث",
        trending: "الرائج",
        all_sources: "كل المصادر",
        watch_now: "شاهد الآن",
        seasons: "المواسم",
        episodes: "الحلقات",
        server: "السيرفر",
        no_results: "لم يتم العثور على نتائج",
        try_different: "جرّب بحثاً أو مصدر آخر",
        paste_url: "الصق رابط",
        paste_url_desc: "أدخل رابط صفحة للسحب",
        scrape: "سحب",
        source: "المصدر",
        year: "السنة",
        type_movie: "فيلم",
        type_series: "مسلسل",
        type_anime: "أنمي",
        back: "رجوع",
        languages: "اللغات",
        quality_select: "الجودة",
        episode: "الحلقة",
        season: "الموسم",
        home: "الرئيسية",
        settings: "الإعدادات",
        clear_cache: "مسح الكاش",
    }
};

let currentLang = localStorage.getItem('lang') || 'en';

function t(key) {
    return I18N[currentLang]?.[key] || I18N.en[key] || key;
}

function setDirection() {
    const html = document.documentElement;
    if (currentLang === 'ar') {
        html.setAttribute('dir', 'rtl');
        html.setAttribute('lang', 'ar');
    } else {
        html.setAttribute('dir', 'ltr');
        html.setAttribute('lang', 'en');
    }
}

function updateUI() {
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (el.placeholder !== undefined && el.tagName === 'INPUT') {
            el.placeholder = t(key);
        } else {
            el.textContent = t(key);
        }
    });
    document.getElementById('langBtn').textContent = currentLang === 'ar' ? 'EN' : 'AR';
    document.getElementById('sourceSelect').querySelector('option').textContent = t('all_sources');
}

function toggleLanguage() {
    currentLang = currentLang === 'en' ? 'ar' : 'en';
    localStorage.setItem('lang', currentLang);
    setDirection();
    updateUI();
}

setDirection();
