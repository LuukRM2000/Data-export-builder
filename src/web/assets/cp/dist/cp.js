(function () {
    function parseJson(value, fallback) {
        try {
            return JSON.parse(value || '');
        } catch (error) {
            return fallback;
        }
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function compareGroups(a, b) {
        if (a === 'Matrix' && b !== 'Matrix') {
            return 1;
        }

        if (b === 'Matrix' && a !== 'Matrix') {
            return -1;
        }

        return a.localeCompare(b);
    }

    function getSelectedRows(root) {
        return Array.from(root.querySelectorAll('[data-selected-row]'));
    }

    function getSelectedPaths(root) {
        return getSelectedRows(root).map((row) => row.dataset.fieldPath);
    }

    function renumberRows(root) {
        getSelectedRows(root).forEach((row, index) => {
            row.querySelector('[data-name="fieldPath"]').name = 'fields[' + index + '][fieldPath]';
            row.querySelector('[data-name="sortOrder"]').name = 'fields[' + index + '][sortOrder]';
            row.querySelector('[data-name="sortOrder"]').value = String(index + 1);
            row.querySelector('[data-name="columnLabel"]').name = 'fields[' + index + '][columnLabel]';
        });

        const emptyState = root.querySelector('[data-selected-empty]');
        if (emptyState) {
            emptyState.classList.toggle('hidden', getSelectedRows(root).length > 0);
        }
    }

    function createSelectedRow(field) {
        const row = document.createElement('div');
        row.className = 'deb-selected-row';
        row.dataset.selectedRow = 'true';
        row.dataset.fieldPath = field.path;
        row.innerHTML = [
            '<div class="deb-selected-row__main">',
            '<input type="hidden" data-name="fieldPath" value="' + escapeHtml(field.path) + '">',
            '<input type="hidden" data-name="sortOrder" value="0">',
            '<div class="deb-selected-path">' + escapeHtml(field.path) + '</div>',
            '<input class="text fullwidth" type="text" data-name="columnLabel" value="' + escapeHtml(field.label) + '" placeholder="Column label">',
            '</div>',
            '<div class="deb-selected-row__actions">',
            '<button type="button" class="btn small" data-move-up>Up</button>',
            '<button type="button" class="btn small" data-move-down>Down</button>',
            '<button type="button" class="btn small" data-remove-field>Remove</button>',
            '</div>'
        ].join('');

        return row;
    }

    function renderAvailableFields(root) {
        const payload = root._payload || { fields: [] };
        const target = root.querySelector('[data-available-fields]');
        const searchTerm = (root.querySelector('[data-field-search]')?.value || '').trim().toLowerCase();
        const selected = new Set(getSelectedPaths(root));
        const groups = {};

        (payload.fields || []).forEach((field) => {
            const haystack = (field.label + ' ' + field.path + ' ' + field.group).toLowerCase();
            if (searchTerm && !haystack.includes(searchTerm)) {
                return;
            }

            groups[field.group] = groups[field.group] || [];
            groups[field.group].push(field);
        });

        const html = Object.keys(groups).sort(compareGroups).map((group) => {
            const fields = groups[group].map((field) => {
                const isSelected = selected.has(field.path);
                return [
                    '<button type="button" class="deb-available-field' + (isSelected ? ' is-selected' : '') + '"',
                    ' data-add-field',
                    ' data-path="' + escapeHtml(field.path) + '"',
                    ' data-label="' + escapeHtml(field.label) + '"',
                    ' data-group="' + escapeHtml(field.group) + '"',
                    isSelected ? ' disabled' : '',
                    '>',
                    '<span class="deb-available-field__label">' + escapeHtml(field.label) + '</span>',
                    '<code>' + escapeHtml(field.path) + '</code>',
                    '</button>'
                ].join('');
            }).join('');

            return '<section class="deb-available-group"><h3>' + escapeHtml(group) + '</h3><div class="deb-available-group__list">' + fields + '</div></section>';
        }).join('');

        target.innerHTML = html || '<p class="light">No matching fields for this element type.</p>';
    }

    function syncSelectOptions(select, options, preferredValue) {
        if (!select) {
            return;
        }

        const currentValue = preferredValue ?? select.value;
        const normalizedOptions = Array.isArray(options) ? options : [];
        const hasCurrentValue = normalizedOptions.some((option) => String(option.value ?? '') === String(currentValue ?? ''));
        const nextValue = hasCurrentValue ? String(currentValue ?? '') : String(normalizedOptions[0]?.value ?? '');

        select.innerHTML = normalizedOptions.map((option) => {
            const value = String(option.value ?? '');
            const label = String(option.label ?? value);

            return '<option value="' + escapeHtml(value) + '">' + escapeHtml(label) + '</option>';
        }).join('');

        select.value = nextValue;
    }

    function syncFilterOptions(root) {
        const payload = root._payload || {};
        const sectionSelect = document.querySelector(root.dataset.sectionSelect || '');
        const siteSelect = document.querySelector(root.dataset.siteFilterTarget || '')?.querySelector('select');
        const formSelect = document.querySelector(root.dataset.formSelect || '');

        syncSelectOptions(sectionSelect, payload.sections || [], sectionSelect?.value);
        syncSelectOptions(siteSelect, payload.sites || [], siteSelect?.value);
        syncSelectOptions(formSelect, payload.forms || [], formSelect?.value);
    }

    function updateFilterVisibility(root) {
        const payload = root._payload || {};
        const sectionRow = document.querySelector(root.dataset.sectionFilterTarget || '');
        const siteRow = document.querySelector(root.dataset.siteFilterTarget || '');
        const formRow = document.querySelector(root.dataset.formFilterTarget || '');
        const populatedToggle = root.querySelector(root.dataset.populatedToggle || '');

        if (sectionRow) {
            sectionRow.classList.toggle('hidden', !payload.supportsSectionFilter);
        }

        if (siteRow) {
            siteRow.classList.toggle('hidden', !payload.supportsSiteFilter);
        }

        if (formRow) {
            formRow.classList.toggle('hidden', !payload.supportsFormFilter);
        }

        if (populatedToggle) {
            populatedToggle.checked = !!payload.onlyPopulated;
            populatedToggle.closest('label')?.classList.toggle('hidden', !payload.supportsPopulatedFilter);
            if (!payload.supportsPopulatedFilter) {
                populatedToggle.checked = false;
            }
        }
    }

    function loadPayload(root, elementType) {
        const url = new URL(root.dataset.fieldsUrl, window.location.origin);
        url.searchParams.set('elementType', elementType);
        const sectionSelect = document.querySelector(root.dataset.sectionSelect || '');
        if (sectionSelect && sectionSelect.value) {
            url.searchParams.set('sectionUid', sectionSelect.value);
        }
        const formSelect = document.querySelector(root.dataset.formSelect || '');
        if (formSelect && formSelect.value) {
            url.searchParams.set('formId', formSelect.value);
        }
        const populatedToggle = root.querySelector(root.dataset.populatedToggle || '');
        if (populatedToggle && populatedToggle.checked) {
            url.searchParams.set('onlyPopulated', '1');
        }

        fetch(url.toString(), {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
            .then((response) => response.json())
            .then((payload) => {
                root._payload = payload;
                syncFilterOptions(root);
                renderAvailableFields(root);
                updateFilterVisibility(root);
            });
    }

    function initPicker(root) {
        root._payload = parseJson(root.dataset.initialPayload, { fields: [] });
        renumberRows(root);
        renderAvailableFields(root);
        updateFilterVisibility(root);

        const elementSelect = document.querySelector(root.dataset.elementSelect || '');
        if (elementSelect) {
            elementSelect.addEventListener('change', function () {
                loadPayload(root, this.value);
            });
        }

        const sectionSelect = document.querySelector(root.dataset.sectionSelect || '');
        if (sectionSelect) {
            sectionSelect.addEventListener('change', function () {
                const currentElementType = elementSelect ? elementSelect.value : (root._payload?.elementType || 'entries');
                loadPayload(root, currentElementType);
            });
        }

        const formSelect = document.querySelector(root.dataset.formSelect || '');
        if (formSelect) {
            formSelect.addEventListener('change', function () {
                const currentElementType = elementSelect ? elementSelect.value : (root._payload?.elementType || 'entries');
                loadPayload(root, currentElementType);
            });
        }

        const populatedToggle = root.querySelector(root.dataset.populatedToggle || '');
        if (populatedToggle) {
            populatedToggle.addEventListener('change', function () {
                const currentElementType = elementSelect ? elementSelect.value : (root._payload?.elementType || 'entries');
                loadPayload(root, currentElementType);
            });
        }

        const search = root.querySelector('[data-field-search]');
        if (search) {
            search.addEventListener('input', function () {
                renderAvailableFields(root);
            });
        }

        root.addEventListener('click', function (event) {
            const target = event.target.closest('button');
            if (!target) {
                return;
            }

            if (target.hasAttribute('data-add-field')) {
                const field = {
                    path: target.dataset.path,
                    label: target.dataset.label
                };

                if (getSelectedPaths(root).includes(field.path)) {
                    return;
                }

                root.querySelector('[data-selected-fields]').appendChild(createSelectedRow(field));
                renumberRows(root);
                renderAvailableFields(root);
                return;
            }

            const row = target.closest('[data-selected-row]');
            if (!row) {
                return;
            }

            if (target.hasAttribute('data-remove-field')) {
                row.remove();
            } else if (target.hasAttribute('data-move-up') && row.previousElementSibling) {
                row.parentNode.insertBefore(row, row.previousElementSibling);
            } else if (target.hasAttribute('data-move-down') && row.nextElementSibling) {
                row.parentNode.insertBefore(row.nextElementSibling, row);
            }

            renumberRows(root);
            renderAvailableFields(root);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-deb-field-picker]').forEach(initPicker);
    });
}());
