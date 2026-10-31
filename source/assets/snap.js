// The sheet: one grid for the drawn lines and the layout. The column is set in whole, even pixels so the background
// tiles and the plates can't drift apart, and every [data-snap] block is rounded up to whole half-modules so its edges
// land on grid lines. [data-fill] also grows to fill the first viewport down to a grid line, where the cover and letter
// sit side by side. The build inlines this at the end of every page's body (App\Services\Assets), so the grid is set
// before the first paint and nothing moves when the site's script arrives; that script runs it again as things change.
(() => {
    const root = document.documentElement;

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
        if (!document.querySelector('.report')) {
            return;
        }

        const half = measure();
        const blocks = [...document.querySelectorAll('[data-snap]')];

        blocks.forEach((block) => (block.style.minHeight = ''));

        // Every measurement first, then every write, so the browser lays the page out once, not once per block.
        const measured = new Map(blocks.map((block) => [block, block.getBoundingClientRect().height]));
        const snapped = new Map();

        // Bottom-up, so a filling block knows how tall what follows it is (as snapped, when that is a block too).
        blocks.reverse().forEach((block) => {
            let height = Math.ceil(measured.get(block) / half) * half;

            if (block.hasAttribute('data-fill') && root.clientWidth > 1100) {
                const next = block.nextElementSibling;
                const below = next ? (snapped.get(next) ?? measured.get(next) ?? next.getBoundingClientRect().height) : 0;
                height = Math.max(height, Math.floor((window.innerHeight - half - below) / half) * half);
            }

            snapped.set(block, height);
        });

        snapped.forEach((height, block) => (block.style.minHeight = `${height}px`));
    };

    window.axisSnap = { measure, snap };
    snap();
})();
