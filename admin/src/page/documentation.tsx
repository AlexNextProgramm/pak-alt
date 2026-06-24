import '../css/page/documentation.scss';

function slug(text: string): string {
    return text
        .trim()
        .toLowerCase()
        .replace(/[^\w\s\u0400-\u04FF-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
}

function clearHighlights(root: ParentNode) {
    root.querySelectorAll('mark.doc-highlight').forEach((mark) => {
        const parent = mark.parentNode;
        if (!parent) return;
        parent.replaceChild(document.createTextNode(mark.textContent ?? ''), mark);
        parent.normalize();
    });
}

function highlightText(node: Text, query: string): void {
    const text = node.textContent ?? '';
    const lower = text.toLowerCase();
    const q = query.toLowerCase();
    let start = 0;
    let index = lower.indexOf(q, start);

    if (index === -1) return;

    const fragment = document.createDocumentFragment();

    while (index !== -1) {
        if (index > start) {
            fragment.appendChild(document.createTextNode(text.slice(start, index)));
        }

        const mark = document.createElement('mark');
        mark.className = 'doc-highlight';
        mark.textContent = text.slice(index, index + query.length);
        fragment.appendChild(mark);

        start = index + query.length;
        index = lower.indexOf(q, start);
    }

    if (start < text.length) {
        fragment.appendChild(document.createTextNode(text.slice(start)));
    }

    node.parentNode?.replaceChild(fragment, node);
}

function highlightInElement(el: HTMLElement, query: string): void {
    if (!query) return;

    const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, {
        acceptNode(node) {
            const parent = node.parentElement;
            if (!parent) return NodeFilter.FILTER_REJECT;
            if (parent.closest('button, script, style, mark')) {
                return NodeFilter.FILTER_REJECT;
            }
            return NodeFilter.FILTER_ACCEPT;
        },
    });

    const nodes: Text[] = [];
    let current = walker.nextNode();
    while (current) {
        nodes.push(current as Text);
        current = walker.nextNode();
    }

    nodes.forEach((node) => highlightText(node, query));
}

function getText(el: HTMLElement): string {
    return el.textContent?.replace(/\s+/g, ' ').trim() ?? '';
}

const page = document.querySelector('.page-documentation') as HTMLElement | null;

if (page) {
    const content = page.querySelector('.page-documentation__content') as HTMLElement | null;
    const nav = page.querySelector('.page-documentation__nav') as HTMLElement | null;
    const searchInput = page.querySelector('.page-documentation__search-input') as HTMLInputElement | null;
    const searchMeta = page.querySelector('.page-documentation__search-meta') as HTMLElement | null;

    if (content && nav) {
        const blocks: HTMLElement[] = [];
        const navItems: { el: HTMLAnchorElement; blockId: string; subId?: string }[] = [];

        const children = Array.from(content.childNodes);
        content.innerHTML = '';

        let currentBlock: HTMLElement | null = null;
        let currentPanel: HTMLElement | null = null;
        let currentSub: HTMLElement | null = null;
        let subPanel: HTMLElement | null = null;

        function closeSub() {
            if (currentSub && subPanel && currentPanel) {
                currentSub.appendChild(subPanel);
                currentPanel.appendChild(currentSub);
            }
            currentSub = null;
            subPanel = null;
        }

        function closeBlock() {
            closeSub();
            if (currentBlock && currentPanel) {
                currentBlock.appendChild(currentPanel);
                content.appendChild(currentBlock);
                blocks.push(currentBlock);
            }
            currentBlock = null;
            currentPanel = null;
            closeSub();
        }

        function startBlock(heading: HTMLHeadingElement) {
            closeBlock();

            const id = slug(heading.textContent ?? '') || `section-${blocks.length + 1}`;
            heading.id = id;

            const block = document.createElement('section');
            block.className = 'doc-block doc-block--open';
            block.dataset.docId = id;

            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'doc-block__toggle';
            toggle.setAttribute('aria-expanded', 'true');
            toggle.innerHTML = `<span class="doc-block__chevron" aria-hidden="true"></span><span class="doc-block__title">${heading.textContent ?? ''}</span>`;

            const panel = document.createElement('div');
            panel.className = 'doc-block__panel';

            block.appendChild(toggle);
            currentBlock = block;
            currentPanel = panel;

            const link = document.createElement('a');
            link.href = `#${id}`;
            link.className = 'page-documentation__nav-link';
            link.textContent = heading.textContent ?? '';
            link.dataset.blockId = id;
            nav.appendChild(link);
            navItems.push({ el: link, blockId: id });

            toggle.addEventListener('click', () => {
                const open = block.classList.toggle('doc-block--open');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }

        function startSub(heading: HTMLHeadingElement) {
            if (!currentPanel) return;

            closeSub();

            const id = slug(heading.textContent ?? '') || `sub-${Math.random().toString(36).slice(2, 8)}`;
            heading.id = id;

            const sub = document.createElement('div');
            sub.className = 'doc-sub doc-sub--open';
            sub.dataset.docId = id;

            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'doc-sub__toggle';
            toggle.setAttribute('aria-expanded', 'true');
            toggle.innerHTML = `<span class="doc-sub__chevron" aria-hidden="true"></span><span>${heading.textContent ?? ''}</span>`;

            const panel = document.createElement('div');
            panel.className = 'doc-sub__panel';

            sub.appendChild(toggle);
            currentSub = sub;
            subPanel = panel;

            const blockId = currentBlock?.dataset.docId ?? '';
            const link = document.createElement('a');
            link.href = `#${id}`;
            link.className = 'page-documentation__nav-link page-documentation__nav-link--sub';
            link.textContent = heading.textContent ?? '';
            link.dataset.blockId = blockId;
            link.dataset.subId = id;
            nav.appendChild(link);
            navItems.push({ el: link, blockId, subId: id });

            toggle.addEventListener('click', () => {
                const open = sub.classList.toggle('doc-sub--open');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }

        function appendNode(node: Node) {
            if (node.nodeType === Node.ELEMENT_NODE && (node as HTMLElement).tagName === 'HR') {
                return;
            }

            if (subPanel) {
                subPanel.appendChild(node);
            } else if (currentPanel) {
                currentPanel.appendChild(node);
            }
        }

        children.forEach((node) => {
            if (node.nodeType !== Node.ELEMENT_NODE) {
                if (node.textContent?.trim()) {
                    appendNode(node.cloneNode(true));
                }
                return;
            }

            const el = node as HTMLElement;
            const tag = el.tagName;

            if (tag === 'H2') {
                startBlock(el as HTMLHeadingElement);
                return;
            }

            if (tag === 'H3') {
                startSub(el as HTMLHeadingElement);
                return;
            }

            appendNode(el.cloneNode(true));
        });

        if (currentSub && subPanel) {
            currentSub.appendChild(subPanel);
            currentPanel?.appendChild(currentSub);
        }
        closeSub();
        closeBlock();

        let emptyState = page.querySelector('.page-documentation__empty') as HTMLElement | null;
        if (!emptyState) {
            emptyState = document.createElement('p');
            emptyState.className = 'page-documentation__empty';
            emptyState.hidden = true;
            emptyState.textContent = 'Ничего не найдено. Попробуйте другой запрос.';
            content.parentElement?.appendChild(emptyState);
        }

        function setActiveNav(id: string) {
            navItems.forEach(({ el, subId }) => {
                const targetId = subId ?? el.dataset.blockId ?? '';
                el.classList.toggle('page-documentation__nav-link--active', targetId === id);
            });
        }

        function expandForMatch(block: HTMLElement, sub?: HTMLElement | null) {
            block.classList.add('doc-block--open');
            block.querySelector('.doc-block__toggle')?.setAttribute('aria-expanded', 'true');
            if (sub) {
                sub.classList.add('doc-sub--open');
                sub.querySelector('.doc-sub__toggle')?.setAttribute('aria-expanded', 'true');
            }
        }

        function applySearch(query: string) {
            const q = query.trim();
            blocks.forEach((block) => clearHighlights(block));
            clearHighlights(nav);

            if (!q) {
                blocks.forEach((block) => {
                    block.classList.remove('doc-block--hidden', 'doc-block--dimmed');
                    block.querySelectorAll('.doc-sub').forEach((sub) => {
                        sub.classList.remove('doc-sub--hidden');
                    });
                });
                navItems.forEach(({ el }) => el.classList.remove('page-documentation__nav-link--hidden'));
                if (emptyState) emptyState.hidden = true;
                if (searchMeta) searchMeta.textContent = '';
                content.hidden = false;
                return;
            }

            let visibleCount = 0;

            blocks.forEach((block) => {
                const subs = Array.from(block.querySelectorAll('.doc-sub')) as HTMLElement[];
                let blockMatch = getText(block).toLowerCase().includes(q.toLowerCase());
                let anySubMatch = false;

                subs.forEach((sub) => {
                    const subMatch = getText(sub).toLowerCase().includes(q.toLowerCase());
                    sub.classList.toggle('doc-sub--hidden', !subMatch && !blockMatch);
                    if (subMatch) {
                        anySubMatch = true;
                        expandForMatch(block, sub);
                        highlightInElement(sub, q);
                    }
                });

                const visible = blockMatch || anySubMatch;
                block.classList.toggle('doc-block--hidden', !visible);
                block.classList.toggle('doc-block--dimmed', false);

                if (visible) {
                    visibleCount += 1;
                    expandForMatch(block);
                    if (blockMatch) {
                        highlightInElement(block, q);
                    }
                }
            });

            navItems.forEach(({ el, blockId, subId }) => {
                const block = blocks.find((b) => b.dataset.docId === blockId);
                if (!block || block.classList.contains('doc-block--hidden')) {
                    el.classList.add('page-documentation__nav-link--hidden');
                    return;
                }

                if (subId) {
                    const sub = block.querySelector(`.doc-sub[data-doc-id="${subId}"]`) as HTMLElement | null;
                    el.classList.toggle('page-documentation__nav-link--hidden', !!sub?.classList.contains('doc-sub--hidden'));
                } else {
                    el.classList.remove('page-documentation__nav-link--hidden');
                }
            });

            if (emptyState) {
                emptyState.hidden = visibleCount > 0;
            }
            content.hidden = visibleCount === 0;

            if (searchMeta) {
                searchMeta.textContent = visibleCount
                    ? `Найдено разделов: ${visibleCount}`
                    : '';
            }
        }

        navItems.forEach(({ el }) => {
            el.addEventListener('click', (event) => {
                event.preventDefault();
                const targetId = el.dataset.subId ?? el.dataset.blockId ?? '';
                const target = document.getElementById(targetId);
                if (!target) return;

                const block = target.closest('.doc-block') as HTMLElement | null;
                const sub = target.closest('.doc-sub') as HTMLElement | null;
                if (block) expandForMatch(block, sub);

                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                setActiveNav(targetId);
            });
        });

        const observer = new IntersectionObserver(
            (entries) => {
                const visible = entries
                    .filter((e) => e.isIntersecting)
                    .sort((a, b) => b.intersectionRatio - a.intersectionRatio);

                if (visible[0]?.target.id) {
                    setActiveNav(visible[0].target.id);
                }
            },
            { rootMargin: '-20% 0px -60% 0px', threshold: [0, 0.25, 0.5] },
        );

        blocks.forEach((block) => {
            const title = block.querySelector('.doc-block__title');
            if (title?.parentElement) {
                const id = block.dataset.docId;
                if (id) {
                    const anchor = document.createElement('span');
                    anchor.id = id;
                    block.insertBefore(anchor, block.firstChild);
                    observer.observe(anchor);
                }
            }
            block.querySelectorAll('.doc-sub__toggle span:last-child').forEach((el) => {
                const sub = el.closest('.doc-sub') as HTMLElement | null;
                if (sub?.dataset.docId) {
                    observer.observe(sub);
                }
            });
        });

        if (searchInput) {
            let timer: ReturnType<typeof setTimeout> | undefined;
            searchInput.addEventListener('input', () => {
                clearTimeout(timer);
                timer = setTimeout(() => applySearch(searchInput.value), 180);
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === '/' && document.activeElement !== searchInput) {
                    const tag = (document.activeElement as HTMLElement | null)?.tagName;
                    if (tag === 'INPUT' || tag === 'TEXTAREA') return;
                    event.preventDefault();
                    searchInput.focus();
                }
                if (event.key === 'Escape' && document.activeElement === searchInput) {
                    searchInput.value = '';
                    applySearch('');
                    searchInput.blur();
                }
            });
        }
    }
}