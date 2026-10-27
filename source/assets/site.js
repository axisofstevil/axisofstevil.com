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

    return schedule;
})();

// Random Audit: pull any pictured work from the register (/audit.json) and
// present it as the exhibit, without leaving the page.
(() => {
    const exhibit = document.querySelector('[data-exhibit]');
    const triggers = document.querySelectorAll('[data-audit]');

    if (!exhibit || triggers.length === 0) {
        return;
    }

    const button = document.querySelector('button[data-audit]');
    const dateFormat = new Intl.DateTimeFormat('en-US', { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'UTC' });
    let register = null;

    const load = async () => {
        if (register === null) {
            const response = await fetch('/audit.json');

            if (!response.ok) {
                throw new Error(`The register answered ${response.status}`);
            }

            register = (await response.json()).filter((work) => work.i);
        }

        return register;
    };

    const show = (work) => {
        const image = exhibit.querySelector('img');
        const links = exhibit.querySelectorAll('a');

        image.src = work.i;
        links.forEach((link) => (link.href = work.a));
        exhibit.querySelector('.exhibit-title').textContent = work.t;
        exhibit.querySelector('[data-division]').textContent = work.d ?? '';
        exhibit.querySelector('[data-filed]').textContent = dateFormat.format(new Date(`${work.y}T00:00:00Z`));

        image.addEventListener('load', snapToGrid, { once: true });
        snapToGrid();

        exhibit.classList.remove('is-new');
        void exhibit.offsetWidth;
        exhibit.classList.add('is-new');
    };

    const audit = async () => {
        if (button) {
            button.setAttribute('aria-busy', 'true');
        }

        try {
            const works = await load();
            const current = exhibit.querySelector('.exhibit-title').getAttribute('href');
            const candidates = works.filter((work) => work.a !== current);
            show(candidates[Math.floor(Math.random() * candidates.length)]);
        } catch (error) {
            if (button) {
                button.textContent = 'The register is unavailable. Try again';
            }
        } finally {
            if (button) {
                button.removeAttribute('aria-busy');
            }
        }
    };

    triggers.forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            if (trigger.tagName === 'A') {
                event.preventDefault();
                document.getElementById('audit').scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            audit();
        });
    });
})();
