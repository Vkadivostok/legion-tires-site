// ==========================================================================
// Legion Tires & Rims - Modern Interactive Engine
// ==========================================================================

document.addEventListener('DOMContentLoaded', () => {
    // Initialize elements
    initNavigation();
    initCatalog();
    initProcessVideo();
    initVisualizer();
    initCalculator();
    initContactForm();
});

/* ==========================================================================
   1. Navigation & Hamburger Menu
   ========================================================================== */
function initNavigation() {
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const navMenu = document.getElementById('navMenu');
    const navLinks = document.querySelectorAll('.nav-link');
    
    // Toggle Mobile Menu
    hamburgerBtn.addEventListener('click', () => {
        navMenu.classList.toggle('active');
        hamburgerBtn.classList.toggle('active');
        
        // Animated hamburger state
        const bars = hamburgerBtn.querySelectorAll('.bar');
        if (hamburgerBtn.classList.contains('active')) {
            bars[0].style.transform = 'rotate(-45deg) translate(-5px, 6px)';
            bars[1].style.opacity = '0';
            bars[2].style.transform = 'rotate(45deg) translate(-5px, -6px)';
        } else {
            bars[0].style.transform = 'none';
            bars[1].style.opacity = '1';
            bars[2].style.transform = 'none';
        }
    });

    // Close menu when clicking link
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            navMenu.classList.remove('active');
            hamburgerBtn.classList.remove('active');
            const bars = hamburgerBtn.querySelectorAll('.bar');
            bars[0].style.transform = 'none';
            bars[1].style.opacity = '1';
            bars[2].style.transform = 'none';
        });
    });

    // Scroll Active Link Highlight
    const sections = document.querySelectorAll('section');
    window.addEventListener('scroll', () => {
        let current = '';
        const scrollPosition = window.pageYOffset + 120;

        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            const sectionHeight = section.clientHeight;
            if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
                current = section.getAttribute('id');
            }
        });

        navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === `#${current}`) {
                link.classList.add('active');
            }
        });
        
        // Sticky Header shrink
        const header = document.querySelector('.header');
        if (window.scrollY > 50) {
            header.style.height = '70px';
            header.style.background = 'rgba(10, 11, 14, 0.9)';
        } else {
            header.style.height = '80px';
            header.style.background = 'rgba(10, 11, 14, 0.75)';
        }
    });
}

/* ==========================================================================
   2. Process Video Showcase
   ========================================================================== */
function initProcessVideo() {
    const video = document.getElementById('processVideo');
    const tabs = document.querySelectorAll('.process-video-tab');

    if (!video || tabs.length === 0) return;

    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReducedMotion) {
        video.removeAttribute('autoplay');
        video.controls = true;
        video.pause();
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const src = tab.getAttribute('data-video-src');

            if (!src || video.currentSrc.endsWith(src)) return;

            tabs.forEach(item => {
                item.classList.remove('active');
                item.setAttribute('aria-pressed', 'false');
            });

            tab.classList.add('active');
            tab.setAttribute('aria-pressed', 'true');
            video.classList.add('is-switching');

            window.setTimeout(() => {
                video.innerHTML = `<source src="${src}" type="video/mp4">`;
                video.load();

                if (!prefersReducedMotion) {
                    video.play().catch(() => {});
                }

                video.classList.remove('is-switching');
            }, 160);
        });
    });
}

/* ==========================================================================
   3. Catalog Section (Data Store, Rendering, Filtering)
   ========================================================================== */

// Tires Mock Database (Kazan branch stock)
const tiresDatabase = [
    { id: 't1', brand: 'Michelin Pilot Sport 4', condition: 'new', season: 'summer', radius: 'R18', price: 14500, inStock: true, image: 'images/used_tires_stock.png', badge: 'Новинка' },
    { id: 't2', brand: 'Nokian Hakkapeliitta 9', condition: 'used', season: 'winter', radius: 'R16', price: 4800, inStock: true, image: 'images/used_tires_stock.png', badge: 'Отл. состояние' },
    { id: 't3', brand: 'Pirelli Ice Zero 2', condition: 'new', season: 'winter', radius: 'R17', price: 9200, inStock: true, image: 'images/used_tires_stock.png', badge: 'Хит продаж' },
    { id: 't4', brand: 'Bridgestone Turanza T005', condition: 'used', season: 'summer', radius: 'R17', price: 5400, inStock: true, image: 'images/used_tires_stock.png', badge: 'Износ 10%' },
    { id: 't5', brand: 'Continental IceContact 3', condition: 'new', season: 'winter', radius: 'R19', price: 17200, inStock: true, image: 'images/used_tires_stock.png', badge: 'Эксклюзив' },
    { id: 't6', brand: 'Yokohama BlueEarth-Es ES32', condition: 'used', season: 'summer', radius: 'R15', price: 3200, inStock: true, image: 'images/used_tires_stock.png', badge: 'Бюджет' },
    { id: 't7', brand: 'Hankook Winter i*cept iZ2', condition: 'used', season: 'winter', radius: 'R16', price: 4100, inStock: true, image: 'images/used_tires_stock.png', badge: 'Япония' },
    { id: 't8', brand: 'Cordiant Sport 3', condition: 'new', season: 'summer', radius: 'R15', price: 4800, inStock: true, image: 'images/used_tires_stock.png', badge: 'В наличии' }
];

// Rims Mock Database
const rimsDatabase = [
    { id: 'r1', brand: 'Vossen CV3 Style', type: 'cast', radius: 'R18', price: 45000, inStock: true, image: 'images/hero_wheel.png', badge: 'Хит' },
    { id: 'r2', brand: 'Rays Volk Racing TE37', type: 'forged', radius: 'R17', price: 85000, inStock: true, image: 'images/powder_paint_base.png', badge: 'Ковка' },
    { id: 'r3', brand: 'BBS Super RS Chrome', type: 'cast', radius: 'R19', price: 62000, inStock: true, image: 'images/hero_wheel.png', badge: 'Эксклюзив' },
    { id: 'r4', brand: 'HRE P101 Custom', type: 'forged', radius: 'R20', price: 120000, inStock: true, image: 'images/powder_paint_base.png', badge: 'Под заказ' },
    { id: 'r5', brand: 'OZ Racing Ultraleggera', type: 'cast', radius: 'R16', price: 38000, inStock: true, image: 'images/hero_wheel.png', badge: 'Легкие' },
    { id: 'r6', brand: 'Work Emotion CR-Kiwami', type: 'cast', radius: 'R18', price: 54000, inStock: true, image: 'images/powder_paint_base.png', badge: 'В наличии' }
];

function initCatalog() {
    // Tabs switching
    const tabButtons = document.querySelectorAll('.tab-btn');
    const catalogContents = document.querySelectorAll('.catalog-content');

    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-target');
            
            tabButtons.forEach(b => b.classList.remove('active'));
            catalogContents.forEach(c => c.classList.remove('active'));
            
            btn.classList.add('active');
            document.getElementById(targetId).classList.add('active');
        });
    });

    // Render initial data
    renderTires(tiresDatabase);
    renderRims(rimsDatabase);

    // Tire Filters
    const tireCondition = document.getElementById('tireCondition');
    const tireSeason = document.getElementById('tireSeason');
    const tireDiameter = document.getElementById('tireDiameter');
    const resetTireBtn = document.getElementById('resetTireFilters');

    function applyTireFilters() {
        const cond = tireCondition.value;
        const season = tireSeason.value;
        const diam = tireDiameter.value;

        const filtered = tiresDatabase.filter(tire => {
            const condMatch = cond === 'all' || tire.condition === cond;
            const seasonMatch = season === 'all' || tire.season === season;
            const diamMatch = diam === 'all' || tire.radius === diam;
            return condMatch && seasonMatch && diamMatch;
        });

        renderTires(filtered);
    }

    tireCondition.addEventListener('change', applyTireFilters);
    tireSeason.addEventListener('change', applyTireFilters);
    tireDiameter.addEventListener('change', applyTireFilters);

    resetTireBtn.addEventListener('click', () => {
        tireCondition.value = 'all';
        tireSeason.value = 'all';
        tireDiameter.value = 'all';
        renderTires(tiresDatabase);
        showToast('Фильтры сброшены', 'Показан полный список шин', 'success');
    });

    // Rim Filters
    const rimType = document.getElementById('rimType');
    const rimDiameter = document.getElementById('rimDiameter');
    const resetRimBtn = document.getElementById('resetRimFilters');

    function applyRimFilters() {
        const type = rimType.value;
        const diam = rimDiameter.value;

        const filtered = rimsDatabase.filter(rim => {
            const typeMatch = type === 'all' || rim.type === type;
            const diamMatch = diam === 'all' || rim.radius === diam;
            return typeMatch && diamMatch;
        });

        renderRims(filtered);
    }

    rimType.addEventListener('change', applyRimFilters);
    rimDiameter.addEventListener('change', applyRimFilters);

    resetRimBtn.addEventListener('click', () => {
        rimType.value = 'all';
        rimDiameter.value = 'all';
        renderRims(rimsDatabase);
        showToast('Фильтры сброшены', 'Показан полный список дисков', 'success');
    });
}

function renderTires(data) {
    const grid = document.getElementById('tiresGrid');
    grid.innerHTML = '';

    if (data.length === 0) {
        grid.innerHTML = `<div class="no-results">По вашему запросу ничего не найдено. Напишите нам, мы привезем под заказ!</div>`;
        return;
    }

    data.forEach(item => {
        const seasonIcon = item.season === 'winter' ? '<i class="fa-solid fa-snowflake text-primary"></i>' : '<i class="fa-solid fa-sun text-secondary"></i>';
        const condLabel = item.condition === 'new' ? 'Новая' : 'Б/У';
        const tagClass = item.condition === 'new' ? 'tag-new' : 'tag-used';
        
        const card = document.createElement('div');
        card.className = 'product-card';
        card.innerHTML = `
            <div class="product-img-box">
                <span class="product-tag ${tagClass}">${condLabel}</span>
                <span class="product-season-badge" title="${item.season === 'winter' ? 'Зимняя резина' : 'Летняя резина'}">
                    ${seasonIcon}
                </span>
                <img src="${item.image}" alt="${item.brand}" class="product-img">
            </div>
            <div class="product-info">
                <h4 class="product-title">${item.brand}</h4>
                <div class="product-meta">
                    <span><i class="fa-solid fa-arrows-left-right"></i> ${item.radius}</span>
                    <span><i class="fa-solid fa-tags"></i> ${item.badge}</span>
                </div>
                <div class="product-price-row">
                    <span class="product-price">${item.price.toLocaleString()} ₽ <small style="font-size:0.7rem; color:var(--color-text-muted);">/ шт.</small></span>
                    <button class="btn btn-primary btn-sm btn-buy" data-product="${item.brand}">Купить</button>
                </div>
            </div>
        `;
        grid.appendChild(card);
    });

    // Add buy click handler
    grid.querySelectorAll('.btn-buy').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const productName = e.target.getAttribute('data-product');
            openBookingForProduct(productName, 'tires-buy');
        });
    });
}

function renderRims(data) {
    const grid = document.getElementById('rimsGrid');
    grid.innerHTML = '';

    if (data.length === 0) {
        grid.innerHTML = `<div class="no-results">Таких дисков сейчас нет на складе. Оставьте заявку на индивидуальный подбор.</div>`;
        return;
    }

    data.forEach(item => {
        const typeLabel = item.type === 'cast' ? 'Литой' : 'Кованый';
        
        const card = document.createElement('div');
        card.className = 'product-card';
        card.innerHTML = `
            <div class="product-img-box">
                <span class="product-tag tag-new">${typeLabel}</span>
                <img src="${item.image}" alt="${item.brand}" class="product-img">
            </div>
            <div class="product-info">
                <h4 class="product-title">${item.brand}</h4>
                <div class="product-meta">
                    <span><i class="fa-solid fa-circle-notch"></i> ${item.radius}</span>
                    <span><i class="fa-solid fa-award"></i> ${item.badge}</span>
                </div>
                <div class="product-price-row">
                    <span class="product-price">${item.price.toLocaleString()} ₽ <small style="font-size:0.7rem; color:var(--color-text-muted);">/ комплект</small></span>
                    <button class="btn btn-primary btn-sm btn-buy" data-product="${item.brand}">Купить</button>
                </div>
            </div>
        `;
        grid.appendChild(card);
    });

    // Add buy click handler
    grid.querySelectorAll('.btn-buy').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const productName = e.target.getAttribute('data-product');
            openBookingForProduct(productName, 'tires-buy');
        });
    });
}

function openBookingForProduct(productName, serviceType) {
    document.getElementById('contacts').scrollIntoView({ behavior: 'smooth' });
    document.getElementById('serviceType').value = serviceType;
    document.getElementById('userMessage').value = `Здравствуйте! Интересует наличие и покупка: ${productName}`;
    showToast('Выбран товар', `Параметры "${productName}" добавлены в форму записи`, 'success');
}

/* ==========================================================================
   3. Interactive Powder Coating Visualizer
   ========================================================================== */
function initVisualizer() {
    const paintWheel = document.getElementById('paintWheel');
    const colorSelectors = document.querySelectorAll('.color-selector');
    const currentColorLabel = document.getElementById('currentColorLabel');
    const finishBtns = document.querySelectorAll('.finish-btn');
    const coatingPriceText = document.getElementById('coatingPriceText');
    const bookCoatingBtn = document.getElementById('bookCoatingBtn');
    
    // High-fidelity color presets using sepia-first CSS colorization technique
    const colorPresets = {
        'original': { sepia: 0, saturate: 1, hue: 0, brightness: 1, contrast: 1 },
        'gloss-black': { sepia: 0, saturate: 0, hue: 0, brightness: 0.15, contrast: 1.1 }, // Satin Black (much darker to display correctly)
        'candy-red': { sepia: 1, saturate: 6, hue: 325, brightness: 0.65, contrast: 1.4 },
        'cyber-blue': { sepia: 1, saturate: 5, hue: 185, brightness: 0.75, contrast: 1.3 },
        'liquid-gold': { sepia: 1, saturate: 3.5, hue: 10, brightness: 0.85, contrast: 1.2 },
        'toxic-green': { sepia: 1, saturate: 6, hue: 75, brightness: 0.75, contrast: 1.3 },
        'burnt-bronze': { sepia: 1, saturate: 2, hue: 15, brightness: 0.5, contrast: 1.3 },
        'white': { sepia: 0, saturate: 0, hue: 0, brightness: 1.65, contrast: 0.9 }, // Gloss White
        'candy-purple': { sepia: 1, saturate: 5, hue: 265, brightness: 0.7, contrast: 1.35 },
        'graphite': { sepia: 0, saturate: 0, hue: 0, brightness: 0.35, contrast: 1.25 }, // Dark Graphite
        'deep-black': { sepia: 0, saturate: 0, hue: 0, brightness: 0.08, contrast: 1.4 }, // Deep Gloss Black
        'chameleon': { sepia: 1, saturate: 5, hue: 280, brightness: 0.6, contrast: 1.4 }, // Chameleon Color Shift
        'emerald': { sepia: 1, saturate: 4.5, hue: 120, brightness: 0.5, contrast: 1.2 } // Emerald Green
    };

    let activeColor = 'original';
    let activeFinish = 'gloss';
    let baseColorName = 'Серебристый алюминий (Оригинал)';
    let basePrice = 5500;

    // Apply filters function
    function updateWheelFilters() {
        const settings = colorPresets[activeColor];
        let finalBright = settings.brightness;
        let finalContrast = settings.contrast;
        
        // Matte coating alters reflection levels
        if (activeFinish === 'matte') {
            finalBright = finalBright * 0.85;
            finalContrast = finalContrast * 1.15;
            paintWheel.style.boxShadow = 'none';
        } else {
            // Gloss glow
            paintWheel.style.boxShadow = '0 15px 35px rgba(0,0,0,0.5)';
        }

        // Apply visual adjustments: SEPIA FIRST is critical to colorize grey metal alloy
        const filterStr = `drop-shadow(0 10px 20px rgba(0, 0, 0, 0.4)) 
                           sepia(${settings.sepia}) 
                           saturate(${settings.saturate}) 
                           hue-rotate(${settings.hue}deg) 
                           brightness(${finalBright}) 
                           contrast(${finalContrast})`;
        
        paintWheel.style.filter = filterStr;

        // Dynamic Glow Background based on colors
        const colorGlow = document.getElementById('colorGlow');
        if (activeColor === 'original') {
            colorGlow.style.backgroundColor = 'transparent';
        } else {
            colorGlow.style.backgroundColor = `hsl(${settings.hue}, 90%, 50%)`;
            colorGlow.style.opacity = '0.12';
        }
    }

    // Color swatches click handler
    colorSelectors.forEach(selector => {
        selector.addEventListener('click', () => {
            colorSelectors.forEach(s => s.classList.remove('active'));
            selector.classList.add('active');

            activeColor = selector.getAttribute('data-color');
            baseColorName = selector.getAttribute('data-label');
            currentColorLabel.innerText = `${baseColorName} (${activeFinish === 'gloss' ? 'Глянец' : 'Матовый'})`;

            // Custom pricing rules based on color types
            if (activeColor === 'original') {
                basePrice = 5000;
            } else if (activeColor === 'candy-red' || activeColor === 'liquid-gold' || activeColor === 'chameleon') {
                basePrice = 7500; // Special finishes cost more
            } else {
                basePrice = 6000;
            }

            updatePriceDisplay();
            updateWheelFilters();
        });
    });

    // Finish (Gloss / Matte) handler
    finishBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            finishBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            activeFinish = btn.getAttribute('data-finish');
            currentColorLabel.innerText = `${baseColorName} (${activeFinish === 'gloss' ? 'Глянец' : 'Матовый'})`;
            
            updatePriceDisplay();
            updateWheelFilters();
        });
    });

    function updatePriceDisplay() {
        let finalPrice = basePrice;
        if (activeFinish === 'matte') {
            finalPrice += 1000; // Matte varnish is more premium
        }
        coatingPriceText.innerText = `${finalPrice.toLocaleString()} ₽`;
    }

    bookCoatingBtn.addEventListener('click', () => {
        document.getElementById('contacts').scrollIntoView({ behavior: 'smooth' });
        document.getElementById('serviceType').value = 'painting';
        document.getElementById('userMessage').value = `Здравствуйте! Хочу записаться на порошковую покраску дисков. Выбрал цвет: ${baseColorName}, финиш: ${activeFinish === 'gloss' ? 'Глянцевый лак' : 'Суперматовый лак'}.`;
        showToast('Покраска выбрана', 'Параметры цвета переданы в форму записи', 'success');
    });
}

/* ==========================================================================
   4. Pricing & Work Calculator (Tire Fitting)
   ========================================================================== */
/* ==========================================================================
   4. Pricing & Work Calculator (Tire Fitting)
   ========================================================================== */
function initCalculator() {
    // Official Pricelist Database from Legion Kazan
    const pricingData = {
        // 1. ЛЕГКОВОЕ АВТО (passenger)
        passenger: {
            R14: { dismount: 250, mount: 275, balance: 300, one: 750, four: 2900 },
            R15: { dismount: 275, mount: 300, balance: 325, one: 775, four: 3000 },
            R16: { dismount: 300, mount: 325, balance: 350, one: 850, four: 3300 },
            R17: { dismount: 325, mount: 350, balance: 375, one: 950, four: 3700 },
            R18: { dismount: 350, mount: 375, balance: 400, one: 1050, four: 4000 },
            R19: { dismount: 375, mount: 400, balance: 425, one: 1100, four: 4300 },
            R20: { dismount: 400, mount: 425, balance: 450, one: 1150, four: 4500 },
            R21: { dismount: 425, mount: 450, balance: 500, one: 1300, four: 5000 },
            R22: { dismount: 450, mount: 500, balance: 550, one: 1400, four: 5500 },
            R23: { dismount: 475, mount: 525, balance: 600, one: 1550, four: 6000 },
            R24: { dismount: 500, mount: 600, balance: 700, one: 1650, four: 6500 }
        },
        // 2. МИНИВЕН, ВНЕДОРОЖНИК, КРОССОВЕР (suv)
        suv: {
            R16: { dismount: 275, mount: 350, balance: 375, one: 900, four: 3500 },
            R17: { dismount: 350, mount: 375, balance: 400, one: 975, four: 3900 },
            R18: { dismount: 375, mount: 400, balance: 450, one: 1100, four: 4300 },
            R19: { dismount: 400, mount: 425, balance: 475, one: 1150, four: 4500 },
            R20: { dismount: 450, mount: 450, balance: 500, one: 1300, four: 5000 },
            R21: { dismount: 475, mount: 475, balance: 550, one: 1400, four: 5500 },
            R22: { dismount: 500, mount: 500, balance: 600, one: 1550, four: 6000 },
            R23: { dismount: 550, mount: 600, balance: 650, one: 1650, four: 6500 },
            R24: { dismount: 600, mount: 650, balance: 750, one: 1800, four: 7000 }
        },
        // 3. Дополнительные опции
        addons: {
            runflat: 150,
            sealant: 150,
            copperHub: 100,
            wash: 75
        },
        // 4. Ремонтные работы
        repairs: {
            straighten: {
                R14: 2000, R15: 2000,
                R16: 2500, R17: 2500,
                R18: 3000, R19: 3000,
                R20: 3500, R21: 3500,
                R22: 4500, R23: 4500, R24: 4500
            },
            plug: 300,
            patch: 1000,
            cordPatch: 2000
        },
        // 5. Расходные материалы
        consumables: {
            blackValve: 125,
            chromeValve: 150,
            bag: 50
        },
        // 6. Сезонное хранение на 6 месяцев
        storage: {
            tiresOnly: {
                R14: 3000, R15: 3000,
                R16: 3500, R17: 3500,
                R18: 4000, R19: 4000,
                R20: 4500, R21: 4500, R22: 4500,
                R23: 5500, R24: 5500
            },
            wheelsSet: {
                R14: 3500, R15: 3500,
                R16: 4000, R17: 4000,
                R18: 4500, R19: 4500,
                R20: 5000, R21: 5000, R22: 5000,
                R23: 6000, R24: 6000
            }
        }
    };

    // DOM Elements
    const vehicleOptions = document.querySelectorAll('.vehicle-option');
    const diameterSelectorContainer = document.getElementById('diameterSelectorContainer');
    const workRadios = document.querySelectorAll('input[name="calcWorkMode"]');
    const customWorkPanel = document.getElementById('customWorkPanel');
    
    // Qty counters
    const qtyMinus = document.getElementById('qtyMinus');
    const qtyPlus = document.getElementById('qtyPlus');
    const qtyVal = document.getElementById('qtyVal');
    
    const blackValveMinus = document.getElementById('blackValveMinus');
    const blackValvePlus = document.getElementById('blackValvePlus');
    const qtyBlackValveVal = document.getElementById('qtyBlackValveVal');
    
    const chromeValveMinus = document.getElementById('chromeValveMinus');
    const chromeValvePlus = document.getElementById('chromeValvePlus');
    const qtyChromeValveVal = document.getElementById('qtyChromeValveVal');
    
    const bagsMinus = document.getElementById('bagsMinus');
    const bagsPlus = document.getElementById('bagsPlus');
    const qtyBagsVal = document.getElementById('qtyBagsVal');
    
    // Checkboxes & Selects
    const workDismount = document.getElementById('workDismount');
    const workMount = document.getElementById('workMount');
    const workBalance = document.getElementById('workBalance');
    
    const addonRunflat = document.getElementById('addonRunflat');
    const addonSealant = document.getElementById('addonSealant');
    const addonCopperHub = document.getElementById('addonCopperHub');
    const addonWash = document.getElementById('addonWash');
    
    const repairStraighten = document.getElementById('repairStraighten');
    const repairPlug = document.getElementById('repairPlug');
    const repairPatch = document.getElementById('repairPatch');
    const repairCordPatch = document.getElementById('repairCordPatch');
    
    const straightenPriceLabel = document.getElementById('straightenPriceLabel');
    const storageOption = document.getElementById('storageOption');
    const storagePricePreviewText = document.getElementById('storagePricePreviewText');
    
    const calcBreakdownContainer = document.getElementById('calcBreakdownContainer');
    const calcTotalPrice = document.getElementById('calcTotalPrice');
    const bookCalcBtn = document.getElementById('bookCalcBtn');

    // Calculator State Variables
    let activeVehicle = 'passenger';
    let activeRadius = 'R15';
    let wheelsCount = 4;
    let qtyBlackValves = 0;
    let qtyChromeValves = 0;
    let qtyBags = 0;

    // Helper functions for Qty Counters
    function setupCounter(minusBtn, plusBtn, valSpan, minVal, maxVal, onChange) {
        minusBtn.addEventListener('click', () => {
            let val = parseInt(valSpan.innerText);
            if (val > minVal) {
                val--;
                valSpan.innerText = val;
                onChange(val);
            }
        });
        plusBtn.addEventListener('click', () => {
            let val = parseInt(valSpan.innerText);
            if (val < maxVal) {
                val++;
                valSpan.innerText = val;
                onChange(val);
            }
        });
    }

    // Connect qty buttons
    setupCounter(qtyMinus, qtyPlus, qtyVal, 1, 4, (val) => { wheelsCount = val; recalculateTotal(); });
    setupCounter(blackValveMinus, blackValvePlus, qtyBlackValveVal, 0, 4, (val) => { qtyBlackValves = val; recalculateTotal(); });
    setupCounter(chromeValveMinus, chromeValvePlus, qtyChromeValveVal, 0, 4, (val) => { qtyChromeValves = val; recalculateTotal(); });
    setupCounter(bagsMinus, bagsPlus, qtyBagsVal, 0, 4, (val) => { qtyBags = val; recalculateTotal(); });

    // Vehicle Selector Click Handler
    vehicleOptions.forEach(opt => {
        opt.addEventListener('click', () => {
            vehicleOptions.forEach(o => o.classList.remove('active'));
            opt.classList.add('active');
            activeVehicle = opt.getAttribute('data-vehicle');
            
            // Adjust radius list (R14-R15 only available for passenger)
            const diameterBtns = diameterSelectorContainer.querySelectorAll('.diameter-btn');
            diameterBtns.forEach(btn => {
                const rad = btn.getAttribute('data-radius');
                if (activeVehicle === 'suv' && (rad === 'R14' || rad === 'R15')) {
                    btn.classList.add('d-none');
                } else {
                    btn.classList.remove('d-none');
                }
            });

            // If selected SUV and current radius was R14 or R15, change to R16
            if (activeVehicle === 'suv' && (activeRadius === 'R14' || activeRadius === 'R15')) {
                activeRadius = 'R16';
                diameterBtns.forEach(btn => {
                    btn.classList.remove('active');
                    if (btn.getAttribute('data-radius') === 'R16') btn.classList.add('active');
                });
            }

            recalculateTotal();
        });
    });

    // Diameter Selector Handler
    function initDiameterListeners() {
        const diameterBtns = diameterSelectorContainer.querySelectorAll('.diameter-btn');
        diameterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                diameterBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                activeRadius = btn.getAttribute('data-radius');
                recalculateTotal();
            });
        });
    }
    initDiameterListeners();

    // Work Mode Handler
    workRadios.forEach(radio => {
        radio.addEventListener('change', () => {
            if (radio.value === 'custom') {
                customWorkPanel.classList.remove('d-none');
            } else {
                customWorkPanel.classList.add('d-none');
            }
            recalculateTotal();
        });
    });

    // Checkbox elements event listeners
    const triggerInputs = [
        workDismount, workMount, workBalance,
        addonRunflat, addonSealant, addonCopperHub, addonWash,
        repairStraighten, repairPlug, repairPatch, repairCordPatch,
        storageOption
    ];
    triggerInputs.forEach(input => {
        input.addEventListener('change', recalculateTotal);
    });

    // Calculate sum logic
    function recalculateTotal() {
        calcBreakdownContainer.innerHTML = '';
        let total = 0;
        const vehicleLabel = activeVehicle === 'passenger' ? 'Легковой' : 'Внедорожник';

        // 1. Calculate Base Price
        const workModeVal = document.querySelector('input[name="calcWorkMode"]:checked').value;
        
        if (workModeVal === 'complex') {
            const complexCost = pricingData[activeVehicle][activeRadius].four;
            total += complexCost;
            addBreakdownItem(`Комплекс 4 колеса (${activeRadius}, ${vehicleLabel})`, complexCost);
        } else {
            // Custom поштучно works
            let pricePerWheel = 0;
            let activeWorksNames = [];

            if (workDismount.checked) {
                pricePerWheel += pricingData[activeVehicle][activeRadius].dismount;
                activeWorksNames.push('съем/уст');
            }
            if (workMount.checked) {
                pricePerWheel += pricingData[activeVehicle][activeRadius].mount;
                activeWorksNames.push('шиномонтаж');
            }
            if (workBalance.checked) {
                pricePerWheel += pricingData[activeVehicle][activeRadius].balance;
                activeWorksNames.push('балансировка');
            }

            if (pricePerWheel > 0) {
                const subTotal = pricePerWheel * wheelsCount;
                total += subTotal;
                addBreakdownItem(`Работы поштучно (${activeRadius}, ${wheelsCount} шт: ${activeWorksNames.join('+')})`, subTotal);
            }
        }

        // 2. Addons
        if (addonRunflat.checked) {
            const cost = pricingData.addons.runflat * wheelsCount;
            total += cost;
            addBreakdownItem(`Шины RunFlat (+150 ₽ × ${wheelsCount})`, cost);
        }
        if (addonSealant.checked) {
            const cost = pricingData.addons.sealant * wheelsCount;
            total += cost;
            addBreakdownItem(`Герметик бортов (+150 ₽ × ${wheelsCount})`, cost);
        }
        if (addonCopperHub.checked) {
            const cost = pricingData.addons.copperHub * wheelsCount;
            total += cost;
            addBreakdownItem(`Медная смазка ступиц (+100 ₽ × ${wheelsCount})`, cost);
        }
        if (addonWash.checked) {
            const cost = pricingData.addons.wash * wheelsCount;
            total += cost;
            addBreakdownItem(`Мойка колес (+75 ₽ × ${wheelsCount})`, cost);
        }

        // 3. Repairs
        // Straightening price changes according to disk radius
        const straightenPrice = pricingData.repairs.straighten[activeRadius];
        straightenPriceLabel.innerText = `${straightenPrice} ₽`;
        
        if (repairStraighten.checked) {
            total += straightenPrice;
            addBreakdownItem(`Правка литого диска (${activeRadius})`, straightenPrice);
        }
        if (repairPlug.checked) {
            const cost = pricingData.repairs.plug;
            total += cost;
            addBreakdownItem('Ремонт шины жгутом', cost);
        }
        if (repairPatch.checked) {
            const cost = pricingData.repairs.patch;
            total += cost;
            addBreakdownItem('Ремонт шины заплаткой', cost);
        }
        if (repairCordPatch.checked) {
            const cost = pricingData.repairs.cordPatch;
            total += cost;
            addBreakdownItem('Ремонт кордовой заплаткой', cost);
        }

        // 4. Consumables
        if (qtyBlackValves > 0) {
            const cost = qtyBlackValves * pricingData.consumables.blackValve;
            total += cost;
            addBreakdownItem(`Вентиль черный (${qtyBlackValves} шт)`, cost);
        }
        if (qtyChromeValves > 0) {
            const cost = qtyChromeValves * pricingData.consumables.chromeValve;
            total += cost;
            addBreakdownItem(`Вентиль хром (${qtyChromeValves} шт)`, cost);
        }
        if (qtyBags > 0) {
            const cost = qtyBags * pricingData.consumables.bag;
            total += cost;
            addBreakdownItem(`Пакеты для колес (${qtyBags} шт)`, cost);
        }

        // 5. Seasonal Storage
        const storageVal = storageOption.value;
        if (storageVal !== 'none') {
            const storageCost = pricingData.storage[storageVal][activeRadius];
            total += storageCost;
            addBreakdownItem(`Сезонное хранение R-типа (${activeRadius})`, storageCost);
            storagePricePreviewText.querySelector('span').innerText = `${storageCost} ₽`;
        } else {
            storagePricePreviewText.querySelector('span').innerText = `0 ₽`;
        }

        // Print final total
        calcTotalPrice.innerText = `${total.toLocaleString()} ₽`;
    }

    function addBreakdownItem(name, price) {
        const div = document.createElement('div');
        div.className = 'breakdown-item';
        div.innerHTML = `
            <span class="item-name">${name}</span>
            <span class="item-price">+${price.toLocaleString()} ₽</span>
        `;
        calcBreakdownContainer.appendChild(div);
    }

    // Run first calculation
    recalculateTotal();

    // Form registration
    bookCalcBtn.addEventListener('click', () => {
        const vehicleText = activeVehicle === 'passenger' ? 'Легковой' : 'Внедорожник';
        const workModeVal = document.querySelector('input[name="calcWorkMode"]:checked').value;
        const workTypeText = workModeVal === 'complex' ? 'Комплексная переобувка (4 шт)' : `Поштучно (${wheelsCount} шт)`;
        
        let details = [];
        const items = calcBreakdownContainer.querySelectorAll('.breakdown-item');
        items.forEach(el => {
            const name = el.querySelector('.item-name').innerText;
            const price = el.querySelector('.item-price').innerText;
            details.push(`- ${name}: ${price}`);
        });

        const detailsStr = details.join('\n');

        document.getElementById('contacts').scrollIntoView({ behavior: 'smooth' });
        document.getElementById('serviceType').value = 'fitting';
        document.getElementById('userMessage').value = `Здравствуйте! Хочу записаться по расчету из калькулятора.
Авто: ${vehicleText}, радиус: ${activeRadius}, режим: ${workTypeText}.

Детализация услуг:\n${detailsStr}\n
Итоговая сумма: ${calcTotalPrice.innerText}`;

        showToast('Расчет перенесен', 'Все услуги из калькулятора загружены в форму записи', 'success');
    });
}

/* ==========================================================================
   5. Interactive Booking Form & Toast Notification System
   ========================================================================== */
function initContactForm() {
    const form = document.getElementById('contactForm');
    
    form.addEventListener('submit', (e) => {
        e.preventDefault();

        // Get values
        const name = document.getElementById('userName').value.trim();
        const phone = document.getElementById('userPhone').value.trim();
        const car = document.getElementById('carModel').value.trim();
        const service = document.getElementById('serviceType').value;
        const msg = document.getElementById('userMessage').value.trim();

        // Basic validations
        if (!name || !phone) {
            showToast('Ошибка заполнения', 'Пожалуйста, заполните обязательные поля с именем и телефоном', 'error');
            return;
        }

        // Simulating backend call success
        showToast(
            'Заявка принята!', 
            `Спасибо, ${name}. Мы получили ваш запрос и свяжемся с вами в течение 10 минут на номер ${phone}.`, 
            'success'
        );

        // Reset form
        form.reset();
    });
}

// Toast alerts generator
function showToast(title, message, type = 'success') {
    const container = document.getElementById('toastContainer');
    
    // Create element
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    const iconClass = type === 'success' ? 'fa-solid fa-circle-check' : 'fa-solid fa-triangle-exclamation';
    
    toast.innerHTML = `
        <div class="toast-icon"><i class="${iconClass}"></i></div>
        <div class="toast-content">
            <h5>${title}</h5>
            <p>${message}</p>
        </div>
        <div class="toast-close"><i class="fa-solid fa-xmark"></i></div>
    `;
    
    // Append
    container.appendChild(toast);

    // Auto remove after 5.5s
    const timeoutId = setTimeout(() => {
        fadeAndRemove(toast);
    }, 5500);

    // Close button click listener
    toast.querySelector('.toast-close').addEventListener('click', () => {
        clearTimeout(timeoutId);
        fadeAndRemove(toast);
    });
}

function fadeAndRemove(element) {
    element.style.opacity = '0';
    element.style.transform = 'translateY(15px)';
    setTimeout(() => {
        if (element.parentNode) {
            element.parentNode.removeChild(element);
        }
    }, 300);
}
