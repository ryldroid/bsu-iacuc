// ===== TERMS / PRIVACY MODAL =====
function openDocModal(e, id) {
    if (e) e.preventDefault();
    const el = document.getElementById(id);
    if (el) el.classList.add('open');
}

function closeDocModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('open');
}

document.addEventListener('click', e => {
    if (e.target.classList && e.target.classList.contains('modal-backdrop') && e.target.classList.contains('open')) {
        e.target.classList.remove('open');
    }
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-backdrop.open').forEach(el => el.classList.remove('open'));
    }
});
