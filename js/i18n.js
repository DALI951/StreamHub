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
        installer_status: "Install",
        installer_done: "Installed",
        installer_save: "Save to device",
        installer_unavailable: "Install not available for this server",
        installer_failed: "Install failed",
        subs_title: "Subtitles",
        subs_none: "No subtitles in this stream",
        subs_upload: "Upload subtitle file (.srt / .vtt)",
        subs_unavailable: "Subtitles not available for this server",
        direct: "Direct stream",
        external: "External player",
        now_playing: "Now playing",
        fresh: "Fresh Releases",
        movies: "Movies",
        series: "Series",
        anime: "Anime",
        player_play: "Play",
        player_pause: "Pause",
        player_mute: "Mute",
        player_unmute: "Unmute",
        player_speed: "Playback speed",
        player_quality: "Quality",
        player_cc: "Subtitles",
        player_fullscreen: "Fullscreen",
        player_crop: "Remove watermark (crop)",
        player_settings: "Settings",
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
        installer_status: "التثبيت",
        installer_done: "تم التثبيت",
        installer_save: "حفظ للجهاز",
        installer_unavailable: "التثبيت غير متاح لهذا السيرفر",
        installer_failed: "فشل التثبيت",
        subs_title: "الترجمة",
        subs_none: "لا توجد ترجمة في هذا المصدر",
        subs_upload: "رفع ملف ترجمة (.srt / .vtt)",
        subs_unavailable: "الترجمة غير متاحة لهذا السيرفر",
        direct: "بث مباشر",
        external: "مشغل خارجي",
        now_playing: "يعمل الآن",
        fresh: "أحدث الإصدارات",
        movies: "أفلام",
        series: "مسلسلات",
        anime: "أنمي",
        player_play: "تشغيل",
        player_pause: "إيقاف",
        player_mute: "كتم الصوت",
        player_unmute: "إلغاء الكتم",
        player_speed: "سرعة التشغيل",
        player_quality: "الجودة",
        player_cc: "الترجمة",
        player_fullscreen: "ملء الشاشة",
        player_crop: "إزالة العلامة المائية (قص)",
        player_settings: "الإعدادات",
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
