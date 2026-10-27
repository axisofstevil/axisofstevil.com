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
