document.addEventListener('DOMContentLoaded', function () {
    const selectAll = document.getElementById('select-all-students');
    const checkboxes = Array.from(document.querySelectorAll('.student-check'));
    const bulkButtons = Array.from(document.querySelectorAll('[data-bulk-status]'));

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            checkboxes.forEach((cb) => {
                cb.checked = selectAll.checked;
            });
        });
    }

    bulkButtons.forEach((button) => {
        button.addEventListener('click', function () {
            const status = button.getAttribute('data-bulk-status');

            checkboxes.forEach((cb) => {
                if (!cb.checked) return;
                const select = document.querySelector(`.status-select[data-student-id="${cb.value}"]`);
                if (select) {
                    select.value = status;
                }
            });
        });
    });
});
