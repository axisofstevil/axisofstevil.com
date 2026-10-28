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

// Random Audit: a run through every work in a shuffled order, kept in this tab
// (sessionStorage) so addresses stay plain. Beginning pulls the first work;
// each work on the run shows its number and the way on (the bar, the foot of
// the work, or R); End returns to where the run began. Nothing is pulled twice
// until every work has been seen.
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
            // End returns to where the run began: this page, at the section it was begun from.
            const section = button?.closest('section[id]');
            run ??= { from: here + window.location.search + (section ? `#${section.id}` : ''), pulled: [], queue: [] };
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
    document.querySelectorAll('[data-audit-no]').forEach((n) => (n.textContent = number));
    document.querySelectorAll('[data-audit-bar], [data-audit-only]').forEach((el) => (el.hidden = false));
    document.querySelectorAll('[data-audit-off]').forEach((el) => (el.hidden = true));
    document.documentElement.classList.add('on-audit');
    snapToGrid();

    document.querySelectorAll('[data-audit-next]').forEach((button) => {
        button.addEventListener('click', () => pull(button, read()));
    });

    document.querySelector('[data-audit-end]')?.addEventListener('click', () => {
        write(null);
        window.location.assign(run.from || '/');
    });

    document.addEventListener('keydown', (event) => {
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
