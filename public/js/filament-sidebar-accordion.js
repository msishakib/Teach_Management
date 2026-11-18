(() => {
    const groupLabels = () =>
        Array.from(document.querySelectorAll('.fi-sidebar-group[data-group-label]'))
            .map((el) => el.dataset.groupLabel)
            .filter(Boolean);

    const activeGroupLabel = () =>
        document.querySelector('.fi-sidebar-group.fi-active[data-group-label]')
            ?.dataset.groupLabel ?? null;

    const ensureArray = (sidebar) => {
        if (!Array.isArray(sidebar.collapsedGroups)) {
            sidebar.collapsedGroups = [];
        }
    };

    const collapseAllExcept = (sidebar, openLabel) => {
        ensureArray(sidebar);
        const labels = groupLabels();

        if (labels.length === 0) {
            return;
        }

        sidebar.collapsedGroups = labels.filter((label) => label !== openLabel);
    };

    const patchStore = () => {
        const sidebar = window.Alpine?.store?.('sidebar');

        if (!sidebar || sidebar.__accordionNav) {
            return;
        }

        sidebar.__accordionNav = true;
        ensureArray(sidebar);

        sidebar.toggleCollapsedGroup = function (group) {
            ensureArray(this);

            const labels = groupLabels();
            const isCollapsed = this.collapsedGroups.includes(group);

            if (isCollapsed) {
                // Opening one group collapses every other group.
                this.collapsedGroups = labels.filter((label) => label !== group);
            } else {
                // Closing the open group leaves the sidebar fully collapsed.
                this.collapsedGroups = labels;
            }
        };

        collapseAllExcept(sidebar, activeGroupLabel());
    };

    const syncToActiveGroup = () => {
        const sidebar = window.Alpine?.store?.('sidebar');

        if (!sidebar) {
            return;
        }

        collapseAllExcept(sidebar, activeGroupLabel());
    };

    document.addEventListener('alpine:initialized', patchStore);
    document.addEventListener('livewire:navigated', syncToActiveGroup);

    if (window.Alpine?.store?.('sidebar')) {
        patchStore();
    }
})();
