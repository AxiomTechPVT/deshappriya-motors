document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector('[data-sidebar]');
    const toggle = document.querySelector('[data-sidebar-toggle]');
    if (sidebar && toggle) toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
});
