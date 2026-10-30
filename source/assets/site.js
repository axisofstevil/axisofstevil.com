// The sheet: one grid for the drawn lines and the layout. The column is set
// in whole, even pixels so the background tiles and the plates can't drift
// apart, and every [data-snap] block is rounded up to whole half-modules so
// its edges land on grid lines. [data-fill] also grows to fill the first
// viewport down to a grid line, where the cover and letter sit side by side.
// Everything a page needs is set up in start() (at the end of this file), which can run again for
// another page; what belongs to the window or the document is bound once, outside it.
let snapToGrid = () => {};

const sheetFor = () => {
    const root = document.documentElement;
    const sheet = document.querySelector('.report');

    if (!sheet) {
        return () => {};
    }

    const measure = () => {
        const style = getComputedStyle(root);
        const cols = parseFloat(style.getPropertyValue('--cols')) || 12;
        const margin = parseFloat(style.getPropertyValue('--margin')) || 24;
        const width = Math.min(root.clientWidth - 2 * margin, 1680);
        const col = Math.floor(width / cols / 2) * 2;

        root.style.setProperty('--col', `${col}px`);
        root.style.setProperty('--sheet', `${col * cols}px`);
        root.style.setProperty('--sheet-left', `${Math.round((root.clientWidth - col * cols) / 2)}px`);

        return col / 2;
    };

    const snap = () => {
        const half = measure();
        const blocks = [...document.querySelectorAll('[data-snap]')];

        blocks.forEach((block) => (block.style.minHeight = ''));

        // Bottom-up, so a filling block knows how tall what follows it is.
        blocks.reverse().forEach((block) => {
            let height = Math.ceil(block.getBoundingClientRect().height / half) * half;

            if (block.hasAttribute('data-fill') && root.clientWidth > 1100) {
                const below = block.nextElementSibling ? block.nextElementSibling.getBoundingClientRect().height : 0;
                height = Math.max(height, Math.floor((window.innerHeight - half - below) / half) * half);
            }

            block.style.minHeight = `${height}px`;
        });
    };

    let frame = 0;
    const schedule = () => {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(snap);
    };

    snap();
    document.fonts?.ready.then(schedule);

    // Pictures and players that arrive later change heights: snap again as each lands.
    document.querySelectorAll('[data-snap] img, [data-snap] iframe, [data-snap] video').forEach((media) => {
        media.addEventListener('load', schedule);
        media.addEventListener('loadedmetadata', schedule);
    });

    return schedule;
};

window.addEventListener('resize', () => snapToGrid());

// The audit's pile: a click deals the top card off and under, and the rest step up.
const startPile = () => {
    const pile = document.querySelector('[data-pile]');
    let cards = pile ? [...pile.querySelectorAll('.audit-card')] : [];

    if (cards.length < 2) {
        return;
    }

    const still = window.matchMedia('(prefers-reduced-motion: reduce)');
    let dealing = false;

    const lay = () =>
        cards.forEach((card, depth) => {
            card.style.setProperty('--d', depth);
            card.style.zIndex = cards.length - depth;
            card.toggleAttribute('data-top', depth === 0);
        });

    lay();

    pile.addEventListener('click', () => {
        if (dealing) {
            return;
        }

        const top = cards[0];
        const under = () => {
            cards = [...cards.slice(1), top];
            lay();
        };

        if (still.matches) {
            under();
            return;
        }

        // Off to the side, then under the pile once it's clear of it.
        dealing = true;
        top.style.transform = 'translate(72%, -16%) rotate(14deg)';
        top.addEventListener(
            'transitionend',
            () => {
                under();
                top.style.transform = '';
                window.setTimeout(() => (dealing = false), 350);
            },
            { once: true },
        );
    });
};

// Random Audit: a run through every work in a shuffled order, kept in this tab
// (sessionStorage) so addresses stay plain. Beginning pulls the first work;
// each work on the run shows its number and the way on (the bar, held at the
// top, or R); End stops the run and leaves the reader on the work.
// Nothing is pulled twice until every work has been seen.
const audit = (() => {
    const key = 'axis.audit';
    const here = () => window.location.pathname;

    const read = () => {
        try {
            return JSON.parse(sessionStorage.getItem(key));
        } catch (error) {
            return null;
        }
    };

    const write = (run) => {
        try {
            if (run) {
                sessionStorage.setItem(key, JSON.stringify(run));
            } else {
                sessionStorage.removeItem(key);
            }
        } catch (error) {
            // Storage refused: the pull still happens, without the run's memory.
        }
    };

    const deal = async () => {
        const response = await fetch('/audit.json');

        if (!response.ok) {
            throw new Error(`The register answered ${response.status}`);
        }

        const list = (await response.json()).map((work) => work.a).filter((address) => address !== here());

        for (let i = list.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [list[i], list[j]] = [list[j], list[i]];
        }

        return list;
    };

    const pull = async (button, run) => {
        button?.setAttribute('aria-busy', 'true');

        try {
            run ??= { pulled: [], queue: [] };
            run.queue = run.queue.filter((address) => address !== here());

            if (run.queue.length === 0) {
                run.queue = await deal();
            }

            const next = run.queue.shift();
            run.pulled.push(next);
            write(run);

            if (window.Turbo) {
                window.Turbo.visit(next);
            } else {
                window.location.assign(next);
            }
        } catch (error) {
            button?.removeAttribute('aria-busy');

            if (button) {
                button.textContent = 'The register is unavailable. Try again';
            }
        }
    };

    // The page's part: Begin, and on the run, where it stands and the way on.
    const start = () => {
        document.querySelectorAll('[data-audit-start]').forEach((trigger) => {
            trigger.addEventListener('click', (event) => {
                event.preventDefault();
                pull(trigger, null);
            });
        });

        const run = read();
        const number = run ? run.pulled.lastIndexOf(here()) + 1 : 0;

        // On the run: show where it stands, and keep it going.
        const show = (on) => {
            document.querySelectorAll('[data-audit-bar]').forEach((el) => (el.hidden = !on));
            document.querySelectorAll('[data-audit-off]').forEach((el) => (el.hidden = on));
            document.documentElement.classList.toggle('on-audit', on);
            snapToGrid();
        };

        if (number === 0) {
            document.documentElement.classList.remove('on-audit');

            return;
        }

        document.querySelectorAll('[data-audit-no]').forEach((n) => (n.textContent = number));
        show(true);

        document.querySelectorAll('[data-audit-next]').forEach((button) => {
            button.addEventListener('click', () => pull(button, read()));
        });

        document.querySelector('[data-audit-end]')?.addEventListener('click', () => {
            write(null);
            show(false);
        });
    };

    document.addEventListener('keydown', (event) => {
        if (!document.documentElement.classList.contains('on-audit')) {
            return;
        }

        const typing = event.target.closest?.('input, textarea, select, [contenteditable]');

        if (event.key.toLowerCase() === 'r' && !event.metaKey && !event.ctrlKey && !event.altKey && !typing) {
            pull(document.querySelector('[data-audit-bar] [data-audit-next]'), read());
        }
    });

    return { start };
})();

// Division pages: the index or the yearbook, remembered for every division.
const startViews = () => {
    const buttons = document.querySelectorAll('.view-switch [data-view]');

    if (buttons.length === 0) {
        return;
    }

    const root = document.documentElement;
    const show = (view) => {
        root.dataset.divisionView = view;
        buttons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.view === view)));
        snapToGrid();
    };

    show(root.dataset.divisionView === 'yearbook' ? 'yearbook' : 'index');

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            show(button.dataset.view);

            try {
                localStorage.setItem('axis.divisionView', button.dataset.view);
            } catch (error) {
                // Private windows can refuse storage; the switch still works for this page.
            }
        });
    });
};

// A series' introduction sits beside its banner, within the banner's height:
// the band is never taller than its banner. Words that run taller step down
// one size (to 14px, never smaller: it must still read as meant) and tighten
// their margins; when they still don't fit, they take their own row beneath
// the banner, at full size.
let bandObserver = null;

const startBands = () => {
    bandObserver?.disconnect();
    const bands = [...document.querySelectorAll('.series-intro')].filter((band) => band.querySelector('.series-banner') && band.querySelector('.series-text'));

    if (bands.length === 0) {
        return;
    }

    const fit = () => {
        bands.forEach((band) => {
            const banner = band.querySelector('.series-banner');
            const text = band.querySelector('.series-text');
            const fits = () => text.offsetLeft >= banner.offsetLeft + banner.offsetWidth && text.offsetHeight <= banner.offsetHeight;

            band.classList.remove('series-intro--stacked');
            text.style.fontSize = '';
            text.style.paddingBlock = '';

            for (const step of [() => {}, () => (text.style.fontSize = '14px'), () => (text.style.paddingBlock = '6px')]) {
                step();

                if (fits()) {
                    return;
                }
            }

            text.style.fontSize = '';
            text.style.paddingBlock = '';
            band.classList.add('series-intro--stacked');
        });
        snapToGrid();
    };

    // Whenever a banner arrives or changes size (a load, the window, the other view shown), measure again.
    let frame = 0;
    const schedule = () => {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(fit);
    };

    fit();
    document.fonts?.ready.then(schedule);

    if ('ResizeObserver' in window) {
        bandObserver = new ResizeObserver(schedule);
        bands.forEach((band) => bandObserver.observe(band.querySelector('.series-banner')));
    } else {
        bands.forEach((band) => band.querySelector('.series-banner').addEventListener('load', schedule));
    }

    document.querySelectorAll('.view-switch [data-view]').forEach((button) => button.addEventListener('click', schedule));
};

// The breadcrumb's switchers: one open at a time, closed on Escape or a click elsewhere.
const startSwitchers = () => {
    const switchers = [...document.querySelectorAll('.switcher')];

    switchers.forEach((switcher) => {
        switcher.addEventListener('toggle', () => {
            if (switcher.open) {
                switchers.forEach((other) => other !== switcher && (other.open = false));
            }
        });

        switcher.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && switcher.open) {
                switcher.open = false;
                switcher.querySelector('summary').focus();
            }
        });
    });

};

document.addEventListener('click', (event) => {
    document.querySelectorAll('.switcher').forEach((switcher) => {
        if (switcher.open && !switcher.contains(event.target)) {
            switcher.open = false;
        }
    });
});

// Flash movies: the thumbnail's button swaps in Ruffle, loaded only when asked.
const ruffleBase = 'https://unpkg.com/@ruffle-rs/ruffle@0.7.1/';
let ruffle = null;

const startFlash = () => {
    const figures = document.querySelectorAll('figure.flash[data-swf]');

    if (figures.length === 0) {
        return;
    }

    const base = ruffleBase;

    const load = () =>
        (ruffle ??= new Promise((resolve, reject) => {
            window.RufflePlayer = window.RufflePlayer || {};
            window.RufflePlayer.config = {
                publicPath: base,
                autoplay: 'on',
                unmuteOverlay: 'hidden',
                splashScreen: false,
                letterbox: 'on',
            };

            const script = document.createElement('script');
            script.src = `${base}ruffle.js`;
            script.onload = () => resolve(window.RufflePlayer.newest());
            script.onerror = () => {
                ruffle = null;
                reject(new Error('Ruffle did not load'));
            };
            document.head.append(script);
        }));

    figures.forEach((figure) => {
        const stage = figure.querySelector('.flash-stage');
        const button = figure.querySelector('.flash-play');
        const image = stage.querySelector('img');

        button.addEventListener('click', async () => {
            button.setAttribute('aria-busy', 'true');

            try {
                const player = (await load()).createPlayer();
                const ratio = image && image.naturalWidth ? image.naturalWidth / image.naturalHeight : 4 / 3;

                player.style.aspectRatio = stage.dataset.ratio ?? String(ratio);
                stage.replaceChildren(player);
                await (player.ruffle?.() ?? player).load({ url: figure.dataset.swf });
                snapToGrid();

                // Larger: the playing movie grows in place over the page (as Zoom does for pictures),
                // fitted to the window at its own proportions; it keeps playing as it opens and closes.
                const [w, h] = (stage.dataset.ratio ?? '').split('/').map(Number);
                stage.style.setProperty('--ratio', w && h ? w / h : ratio);

                const enlarge = document.createElement('button');
                enlarge.type = 'button';
                enlarge.className = 'flash-enlarge';
                enlarge.innerHTML = '<svg viewBox="0 0 12 12" aria-hidden="true"><path d="M1.5 4.5v-3h3M7.5 1.5h3v3M10.5 7.5v3h-3M4.5 10.5h-3v-3" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>Enlarge';

                const close = document.createElement('button');
                close.type = 'button';
                close.className = 'flash-close';
                close.textContent = 'Close';

                const shut = () => {
                    figure.classList.remove('is-enlarged');
                    figure.style.minHeight = '';
                    document.documentElement.classList.remove('is-enlarged-open');
                    document.removeEventListener('keydown', onKey);
                    enlarge.focus();
                };
                const onKey = (event) => event.key === 'Escape' && shut();

                enlarge.addEventListener('click', () => {
                    // Hold the movie's place on the page while it's out of it.
                    figure.style.minHeight = `${figure.offsetHeight}px`;
                    figure.classList.add('is-enlarged');
                    document.documentElement.classList.add('is-enlarged-open');
                    document.addEventListener('keydown', onKey);
                    close.focus();
                });
                close.addEventListener('click', shut);

                // Offered only where the overlay would be meaningfully larger (a wide movie on an upright phone isn't).
                const worth = () => {
                    const r = Number(stage.style.getPropertyValue('--ratio')) || ratio;
                    const room = Math.min(window.innerWidth - 48, (window.innerHeight - 96) * r);
                    enlarge.hidden = !figure.classList.contains('is-enlarged') && room < player.getBoundingClientRect().width * 1.15;
                };
                worth();
                window.addEventListener('resize', worth);
                stage.addEventListener('click', (event) => event.target === stage && figure.classList.contains('is-enlarged') && shut());
                stage.append(enlarge, close);
            } catch (error) {
                button.removeAttribute('aria-busy');

                if (!figure.querySelector('.flash-error')) {
                    figure.insertAdjacentHTML('beforeend', '<p class="flash-error">The movie could not be played here.</p>');
                }
            }
        });
    });
};

// The masthead's ticker: the week's headlines from the world outside, shown one at a time in a
// random order, each for a few seconds. It rests while pointed at, and under reduced motion it
// stays on the first.
let tickers = [];

const startTicker = () => {
    tickers.forEach((timer) => window.clearInterval(timer));
    tickers = [];

    document.querySelectorAll('[data-ticker]').forEach((slot) => {
    const lines = [...slot.children];

    if (lines.length < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    for (let i = lines.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [lines[i], lines[j]] = [lines[j], lines[i]];
    }

    let at = lines.findIndex((line) => line.classList.contains('is-on'));
    let resting = false;

    slot.parentElement.addEventListener('pointerenter', () => (resting = true));
    slot.parentElement.addEventListener('pointerleave', () => (resting = false));

    tickers.push(window.setInterval(() => {
        if (resting) {
            return;
        }

        lines[at].classList.remove('is-on');
        at = (at + 1) % lines.length;
        lines[at].classList.add('is-on');
    }, 6000));
    });
};

// A comic of several pictures: arrows and the count follow the slides, which also scroll by swipe or keys.
const startSlideshows = () => document.querySelectorAll('[data-slideshow]').forEach((show) => {
    const track = show.querySelector('.slides');
    const slides = [...track.children];
    const at = show.querySelector('[data-slide-at]');
    const [back, on] = show.querySelectorAll('[data-slide]');
    const current = () => Math.round(track.scrollLeft / track.clientWidth);
    const still = window.matchMedia('(prefers-reduced-motion: reduce)');

    const update = () => {
        const index = current();
        at.textContent = index + 1;
        back.disabled = index === 0;
        on.disabled = index === slides.length - 1;
    };

    [back, on].forEach((button) =>
        button.addEventListener('click', () => {
            const next = Math.min(slides.length - 1, Math.max(0, current() + Number(button.dataset.slide)));
            track.scrollTo({ left: next * track.clientWidth, behavior: still.matches ? 'auto' : 'smooth' });
        }),
    );

    track.addEventListener('scroll', () => window.requestAnimationFrame(update), { passive: true });
    update();
});

// A lead picture shown smaller than its file: Zoom (or a click on it) opens the set it belongs
// to over the page, each picture as large as the window allows, swiped (or stepped by buttons
// and arrow keys) like the slideshow on the page; pinch to look closer on a phone.
const zoom = (() => {
    // Not a Flash movie's thumbnail: a click on it plays the movie.
    let pictures = [];

    let view = null;
    let set = [];
    // The picture in view, kept as it changes (a closed dialog has no width to measure).
    let shown = 0;

    const track = () => view.querySelector('.zoom-track');
    const current = () => Math.round(track().scrollLeft / Math.max(1, track().clientWidth));
    const still = window.matchMedia('(prefers-reduced-motion: reduce)');

    const update = () => {
        const at = current();
        shown = at;
        const several = set.length > 1;
        view.querySelector('.zoom-count').hidden = !several;
        view.querySelector('.zoom-count b').textContent = at + 1;
        view.querySelectorAll('.zoom-step').forEach((button) => {
            button.hidden = !several;
            button.disabled = at + Number(button.dataset.step) < 0 || at + Number(button.dataset.step) >= set.length;
        });
    };

    const go = (index, smooth = true) => {
        const next = Math.min(set.length - 1, Math.max(0, index));
        track().scrollTo({ left: next * track().clientWidth, behavior: smooth && !still.matches ? 'smooth' : 'auto' });
    };

    const build = () => {
        view = document.createElement('dialog');
        view.className = 'zoom-view';
        view.setAttribute('aria-label', 'Full size');
        view.innerHTML = '<div class="zoom-track"></div>'
            + '<button type="button" class="zoom-step" data-step="-1" aria-label="Previous picture">←</button>'
            + '<button type="button" class="zoom-step" data-step="1" aria-label="Next picture">→</button>'
            + '<p class="zoom-count" aria-live="polite"><b>1</b> of <span>1</span></p>'
            + '<button type="button" class="zoom-close">Close</button>';

        view.querySelector('.zoom-close').addEventListener('click', () => view.close());
        view.querySelectorAll('.zoom-step').forEach((button) => button.addEventListener('click', () => go(current() + Number(button.dataset.step))));
        track().addEventListener('scroll', () => window.requestAnimationFrame(update), { passive: true });

        // A click on the dark around a picture closes it.
        track().addEventListener('click', (event) => {
            if (event.target.classList.contains('zoom-slide')) {
                view.close();
            }
        });

        // Back on the page, a slideshow shows the picture last viewed.
        view.addEventListener('close', () => {
            const picture = set[shown];
            const pageTrack = picture?.closest('.slides');
            const slide = picture?.closest('.slide');

            if (pageTrack && slide) {
                pageTrack.scrollTo({ left: [...pageTrack.children].indexOf(slide) * pageTrack.clientWidth });
            }
        });

        document.body.append(view);
    };

    const open = (picture) => {
        // Built once, and again when a page swap has taken it away.
        if (view === null || !view.isConnected) {
            build();
        }

        set = pictures.filter((other) => other.closest('.work-plate, .work-hero, .gallery-grid') === picture.closest('.work-plate, .work-hero, .gallery-grid'));
        track().replaceChildren(...set.map((one) => {
            const slide = document.createElement('div');
            slide.className = 'zoom-slide';
            const image = document.createElement('img');
            image.src = one.currentSrc || one.src;
            image.alt = one.alt;
            image.width = one.naturalWidth;
            image.height = one.naturalHeight;
            slide.append(image);

            return slide;
        }));
        view.querySelector('.zoom-count span').textContent = set.length;

        view.showModal();
        view.querySelector('.zoom-close').focus();
        go(set.indexOf(picture), false);
        update();
    };

    const offer = (picture) => {
        // A photograph on the Gallery's sheet: the tile itself opens it, with no button over each.
        if (picture.closest('.gallery-grid')) {
            picture.addEventListener('click', () => open(picture));

            return;
        }

        // Only when the file is meaningfully bigger than what's shown.
        if (picture.closest('.zoomable') || picture.naturalWidth < picture.clientWidth * 1.15) {
            return;
        }

        const frame = document.createElement('span');
        frame.className = 'zoomable';
        picture.replaceWith(frame);
        frame.append(picture);

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'zoom-open';
        button.innerHTML = '<svg viewBox="0 0 12 12" aria-hidden="true"><circle cx="5" cy="5" r="3.5" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M7.6 7.6 11 11" stroke="currentColor" stroke-width="1.5"/></svg>Zoom';
        button.setAttribute('aria-label', 'View this picture full size');
        frame.append(button);

        button.addEventListener('click', () => open(picture));
        picture.addEventListener('click', () => open(picture));
    };

    // While it's open, the arrow keys step through the set (not the page behind).
    document.addEventListener('keydown', (event) => {
        if (view?.open && (event.key === 'ArrowLeft' || event.key === 'ArrowRight')) {
            event.preventDefault();
            go(current() + (event.key === 'ArrowLeft' ? -1 : 1));
        }
    });

    const start = () => {
        pictures = [...document.querySelectorAll('.work-plate img, .work-hero img, .gallery-grid img')].filter((picture) => !picture.closest('figure.flash'));

        pictures.forEach((picture) => {
            if (picture.complete && picture.naturalWidth > 0) {
                offer(picture);
            } else {
                picture.addEventListener('load', () => offer(picture), { once: true });
            }
        });
    };

    return { start };
})();

// A story of pages (a choose-your-own-adventure): one page at a time, the one the address names
// (#page-5), else the first; a choice changes the address, and Back goes back a page.
let storyShow = null;

window.addEventListener('hashchange', () => storyShow?.(true));

const startStory = () => {
    storyShow = null;

    document.querySelectorAll('[data-story]').forEach((story) => {
    const pages = [...story.querySelectorAll('[data-story-page]')];

    if (pages.length < 2) {
        return;
    }

    const show = (scroll) => {
        const named = pages.find((page) => `#${page.id}` === window.location.hash) ?? pages[0];
        pages.forEach((page) => (page.hidden = page !== named));
        story.classList.add('is-paged');
        snapToGrid();

        if (scroll) {
            story.closest('article')?.scrollIntoView({ block: 'start' });
        }
    };

    show(false);
    storyShow = show;
    });
};

// The player: the Tunes of the Week, one at a time, in a bar along the foot of the window. A Play
// button anywhere on a page (data-play="artist/track") starts that tune; the bar steps through the
// playlist every page carries. It remembers the tune and where it was, and offers it back paused on
// the next page (a browser won't start sound without a tap).
let reflectPlayer = () => {};

(() => {
    const bar = document.querySelector('[data-player]');
    const source = document.getElementById('playlist');

    if (!bar || !source) {
        return;
    }

    const playlist = JSON.parse(source.textContent);
    const audio = new Audio();
    audio.preload = 'metadata';
    let current = null;
    const picture = bar.querySelector('.player-picture');
    const scrub = bar.querySelector('.player-scrub');
    const toggle = bar.querySelector('.player-toggle');
    const autoplay = bar.querySelector('.player-autoplay');

    // Going on to the next tune when one ends: the switch in the bar, remembered.
    try {
        autoplay.checked = localStorage.getItem('axis.autoplay') !== 'off';
    } catch (e) {
        // Storage may be closed to us: on, as the switch says.
    }

    autoplay.addEventListener('change', () => {
        try {
            localStorage.setItem('axis.autoplay', autoplay.checked ? 'on' : 'off');
        } catch (e) {
            // Nothing remembered.
        }
    });

    // The tunes previous and next step through: an artist's own on their page, else every tune.
    const queue = () => {
        const scope = document.querySelector('[data-player-scope]')?.dataset.playerScope;
        const within = scope ? playlist.filter((tune) => tune.artist_slug === scope) : [];

        return within.length > 0 ? within : playlist;
    };
    const clock = (seconds) => `${Math.floor(seconds / 60)}:${String(Math.floor(seconds % 60)).padStart(2, '0')}`;

    const remember = () => {
        try {
            if (current) {
                localStorage.setItem('axis.tune', JSON.stringify({ id: current.id, at: audio.currentTime }));
            } else {
                localStorage.removeItem('axis.tune');
            }
        } catch (e) {
            // Storage may be closed to us: nothing is remembered.
        }
    };

    const reflect = () => {
        const playing = !audio.paused;
        reflectPlayer = reflect;
        bar.classList.toggle('is-playing', playing);
        toggle.setAttribute('aria-label', playing ? 'Pause' : 'Play');
        document.querySelectorAll('[data-play]').forEach((button) => {
            const on = current !== null && button.dataset.play === current.id;
            button.classList.toggle('is-current', on);
            button.classList.toggle('is-playing', on && playing);
            button.setAttribute('aria-pressed', on && playing ? 'true' : 'false');
        });
    };

    const show = (tune) => {
        current = tune;
        audio.src = tune.audio;
        picture.src = tune.image || '';
        picture.hidden = !tune.image;
        bar.querySelector('.player-track').textContent = tune.title;
        bar.querySelector('.player-artist').textContent = tune.artist;
        bar.hidden = false;
        document.documentElement.classList.add('has-player');

        if ('mediaSession' in navigator) {
            navigator.mediaSession.metadata = new MediaMetadata({ title: tune.title, artist: tune.artist, album: 'Tune of the Week · The Axis of Stevil', artwork: tune.image ? [{ src: tune.image }] : [] });
        }

        reflect();
    };

    const play = (tune) => {
        if (current?.id !== tune.id) {
            show(tune);
        }

        audio.play().catch(() => reflect());
    };

    const step = (by) => {
        if (!current) {
            return;
        }

        const list = queue();
        const at = list.findIndex((tune) => tune.id === current.id);
        // Playing something from outside the page's list: next starts it, previous ends it.
        const next = at < 0 ? list[by > 0 ? 0 : list.length - 1] : list[(at + by + list.length) % list.length];
        play(next);
    };

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-play]');

        if (!button) {
            return;
        }

        const tune = playlist.find((one) => one.id === button.dataset.play);

        if (!tune) {
            return;
        }

        if (current?.id === tune.id && !audio.paused) {
            audio.pause();
        } else {
            play(tune);
        }
    });

    // The bar's own button with nothing loaded yet: the page's tune, else the first of the playlist.
    toggle.addEventListener('click', () => {
        if (!current) {
            const onPage = document.querySelector('[data-play]');
            play(playlist.find((one) => one.id === onPage?.dataset.play) ?? playlist[0]);

            return;
        }

        audio.paused ? audio.play().catch(() => reflect()) : audio.pause();
    });
    bar.querySelectorAll('.player-step').forEach((button) => button.addEventListener('click', () => step(Number(button.dataset.step))));
    bar.querySelector('.player-close').addEventListener('click', () => {
        audio.pause();
        audio.removeAttribute('src');
        current = null;
        bar.hidden = true;
        document.documentElement.classList.remove('has-player');
        remember();
        reflect();
    });

    scrub.addEventListener('input', () => {
        if (audio.duration) {
            audio.currentTime = (scrub.value / 1000) * audio.duration;
        }
    });

    audio.addEventListener('timeupdate', () => {
        if (audio.duration) {
            scrub.value = Math.round((audio.currentTime / audio.duration) * 1000);
            bar.querySelector('.player-at').textContent = clock(audio.currentTime);
        }
    });
    audio.addEventListener('loadedmetadata', () => (bar.querySelector('.player-length').textContent = clock(audio.duration)));
    audio.addEventListener('play', reflect);
    audio.addEventListener('pause', () => {
        reflect();
        remember();
    });
    audio.addEventListener('ended', () => (autoplay.checked ? step(1) : reflect()));
    window.addEventListener('pagehide', remember);
    setInterval(() => !audio.paused && remember(), 5000);

    if ('mediaSession' in navigator) {
        navigator.mediaSession.setActionHandler('play', () => audio.play());
        navigator.mediaSession.setActionHandler('pause', () => audio.pause());
        navigator.mediaSession.setActionHandler('previoustrack', () => step(-1));
        navigator.mediaSession.setActionHandler('nexttrack', () => step(1));
    }

    // The tune from the last page, back where it was, paused until tapped.
    try {
        const saved = JSON.parse(localStorage.getItem('axis.tune') || 'null');
        const tune = saved && playlist.find((one) => one.id === saved.id);

        if (tune) {
            show(tune);
            audio.addEventListener('loadedmetadata', () => (audio.currentTime = Math.min(saved.at || 0, audio.duration || 0)), { once: true });
        }
    } catch (e) {
        // Nothing remembered.
    }
})();

// A page's setup, in the order the file gives it; it can run again for another page.
const start = () => {
    snapToGrid = sheetFor();
    startPile();
    audit.start();
    startViews();
    startBands();
    startSwitchers();
    startFlash();
    startTicker();
    startSlideshows();
    zoom.start();
    startStory();
    reflectPlayer();
};

// With Turbo, each visit swaps the body and the setup runs again (turbo:load fires for the first page too).
if (window.Turbo) {
    document.addEventListener('turbo:load', start);
} else {
    start();
}
