// The sheet: one grid for the drawn lines and the layout. The column is set
// in whole, even pixels so the background tiles and the plates can't drift
// apart, and every [data-snap] block is rounded up to whole half-modules so
// its edges land on grid lines. [data-fill] also grows to fill the first
// viewport down to a grid line, where the cover and letter sit side by side.
const snapToGrid = (() => {
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
    window.addEventListener('resize', schedule);

    // Pictures and players that arrive later change heights: snap again as each lands.
    document.querySelectorAll('[data-snap] img, [data-snap] iframe, [data-snap] video').forEach((media) => {
        media.addEventListener('load', schedule);
        media.addEventListener('loadedmetadata', schedule);
    });

    return schedule;
})();

// The audit's pile: a click deals the top card off and under, and the rest step up.
(() => {
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
})();

// Random Audit: a run through every work in a shuffled order, kept in this tab
// (sessionStorage) so addresses stay plain. Beginning pulls the first work;
// each work on the run shows its number and the way on (the bar, held at the
// top, or R); End stops the run and leaves the reader on the work.
// Nothing is pulled twice until every work has been seen.
(() => {
    const key = 'axis.audit';
    const here = window.location.pathname;

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

        const list = (await response.json()).map((work) => work.a).filter((address) => address !== here);

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
            run.queue = run.queue.filter((address) => address !== here);

            if (run.queue.length === 0) {
                run.queue = await deal();
            }

            const next = run.queue.shift();
            run.pulled.push(next);
            write(run);
            window.location.assign(next);
        } catch (error) {
            button?.removeAttribute('aria-busy');

            if (button) {
                button.textContent = 'The register is unavailable. Try again';
            }
        }
    };

    document.querySelectorAll('[data-audit-start]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            pull(trigger, null);
        });
    });

    const run = read();
    const number = run ? run.pulled.lastIndexOf(here) + 1 : 0;

    if (number === 0) {
        return;
    }

    // On the run: show where it stands, and keep it going.
    const show = (on) => {
        document.querySelectorAll('[data-audit-bar]').forEach((el) => (el.hidden = !on));
        document.querySelectorAll('[data-audit-off]').forEach((el) => (el.hidden = on));
        document.documentElement.classList.toggle('on-audit', on);
        snapToGrid();
    };

    document.querySelectorAll('[data-audit-no]').forEach((n) => (n.textContent = number));
    show(true);

    document.querySelectorAll('[data-audit-next]').forEach((button) => {
        button.addEventListener('click', () => pull(button, read()));
    });

    document.querySelector('[data-audit-end]')?.addEventListener('click', () => {
        write(null);
        show(false);
    });

    document.addEventListener('keydown', (event) => {
        if (!document.documentElement.classList.contains('on-audit')) {
            return;
        }

        const typing = event.target.closest?.('input, textarea, select, [contenteditable]');

        if (event.key.toLowerCase() === 'r' && !event.metaKey && !event.ctrlKey && !event.altKey && !typing) {
            pull(document.querySelector('[data-audit-bar] [data-audit-next]'), read());
        }
    });
})();

// Division pages: the index or the yearbook, remembered for every division.
(() => {
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
})();

// The breadcrumb's switchers: one open at a time, closed on Escape or a click elsewhere.
(() => {
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

    document.addEventListener('click', (event) => {
        switchers.forEach((switcher) => {
            if (switcher.open && !switcher.contains(event.target)) {
                switcher.open = false;
            }
        });
    });
})();

// Flash movies: the thumbnail's button swaps in Ruffle, loaded only when asked.
(() => {
    const figures = document.querySelectorAll('figure.flash[data-swf]');

    if (figures.length === 0) {
        return;
    }

    const base = 'https://unpkg.com/@ruffle-rs/ruffle@0.7.1/';
    let ruffle = null;

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

                player.style.aspectRatio = String(ratio);
                stage.replaceChildren(player);
                await (player.ruffle?.() ?? player).load({ url: figure.dataset.swf });
                snapToGrid();
            } catch (error) {
                button.removeAttribute('aria-busy');

                if (!figure.querySelector('.flash-error')) {
                    figure.insertAdjacentHTML('beforeend', '<p class="flash-error">The movie could not be played here.</p>');
                }
            }
        });
    });
})();

// A comic of several pictures: arrows and the count follow the slides, which also scroll by swipe or keys.
document.querySelectorAll('[data-slideshow]').forEach((show) => {
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

// A lead picture shown smaller than its file: Zoom (or a click on it) opens it full size.
// A set of pictures (a slideshow) steps through the whole set there, by buttons or arrow keys.
(() => {
    // Not a Flash movie's thumbnail: a click on it plays the movie.
    const pictures = [...document.querySelectorAll('.work-plate img, .work-hero img')].filter((picture) => !picture.closest('figure.flash'));

    if (pictures.length === 0) {
        return;
    }

    let view = null;
    let set = [];
    let at = 0;

    const show = () => {
        const picture = set[at];
        const image = view.querySelector('.zoom-stage img');
        image.src = picture.currentSrc || picture.src;
        image.alt = picture.alt;
        image.width = picture.naturalWidth;
        image.height = picture.naturalHeight;

        const several = set.length > 1;
        view.querySelector('.zoom-count').hidden = !several;
        view.querySelector('.zoom-count b').textContent = at + 1;
        view.querySelector('.zoom-count span').textContent = set.length;
        view.querySelectorAll('.zoom-step').forEach((button) => {
            button.hidden = !several;
            button.disabled = at + Number(button.dataset.step) < 0 || at + Number(button.dataset.step) >= set.length;
        });

        // Larger than the window: start from its middle.
        const stage = view.querySelector('.zoom-stage');
        stage.scrollLeft = (stage.scrollWidth - stage.clientWidth) / 2;
        stage.scrollTop = (stage.scrollHeight - stage.clientHeight) / 2;
    };

    const step = (by) => {
        if (at + by >= 0 && at + by < set.length) {
            at += by;
            show();
        }
    };

    const build = () => {
        view = document.createElement('dialog');
        view.className = 'zoom-view';
        view.setAttribute('aria-label', 'Full size');
        view.innerHTML = '<div class="zoom-stage"><img alt=""></div>'
            + '<button type="button" class="zoom-step" data-step="-1" aria-label="Previous picture">←</button>'
            + '<button type="button" class="zoom-step" data-step="1" aria-label="Next picture">→</button>'
            + '<p class="zoom-count" aria-live="polite"><b>1</b> of <span>1</span></p>'
            + '<button type="button" class="zoom-close">Close</button>';
        view.querySelector('.zoom-close').addEventListener('click', () => view.close());
        view.querySelectorAll('.zoom-step').forEach((button) => button.addEventListener('click', () => step(Number(button.dataset.step))));
        view.querySelector('.zoom-stage').addEventListener('click', (event) => {
            if (event.target === event.currentTarget || event.target.tagName === 'IMG') {
                view.close();
            }
        });
        // While it's open, the arrow keys step through the set (not the page behind).
        document.addEventListener('keydown', (event) => {
            if (view.open && (event.key === 'ArrowLeft' || event.key === 'ArrowRight')) {
                event.preventDefault();
                step(event.key === 'ArrowLeft' ? -1 : 1);
            }
        });
        // Back on the page, a slideshow shows the picture last viewed.
        view.addEventListener('close', () => {
            const track = set[at]?.closest('.slides');
            const slide = set[at]?.closest('.slide');

            if (track && slide) {
                track.scrollTo({ left: [...track.children].indexOf(slide) * track.clientWidth });
            }
        });
        document.body.append(view);
    };

    const open = (picture) => {
        if (view === null) {
            build();
        }

        set = pictures.filter((other) => other.closest('.work-plate, .work-hero') === picture.closest('.work-plate, .work-hero'));
        at = set.indexOf(picture);
        show();
        view.showModal();
        view.querySelector('.zoom-close').focus();
        show();
    };

    const offer = (picture) => {
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

    pictures.forEach((picture) => {
        if (picture.complete && picture.naturalWidth > 0) {
            offer(picture);
        } else {
            picture.addEventListener('load', () => offer(picture), { once: true });
        }
    });
})();
