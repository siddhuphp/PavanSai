'use strict';
const menu = document.querySelector('.menu-toggle');
const navigation = document.querySelector('#navigation');
menu?.addEventListener('click', () => {
  const expanded = menu.getAttribute('aria-expanded') === 'true';
  menu.setAttribute('aria-expanded', String(!expanded));
  navigation.classList.toggle('open', !expanded);
});
document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && menu?.getAttribute('aria-expanded') === 'true') {
    menu.setAttribute('aria-expanded', 'false'); navigation.classList.remove('open'); menu.focus();
  }
});
document.querySelectorAll('[data-year]').forEach(el => el.textContent = new Date().getFullYear());
document.querySelectorAll('[data-filter]').forEach(button => button.addEventListener('click', () => {
  const filter = button.dataset.filter;
  document.querySelectorAll('[data-filter]').forEach(el => {
    el.classList.toggle('active', el === button); el.setAttribute('aria-pressed', String(el === button));
  });
  let count = 0;
  document.querySelectorAll('[data-category]').forEach(card => {
    card.hidden = filter !== 'All' && card.dataset.category !== filter;
    if (!card.hidden) count++;
  });
  document.querySelector('#work-count').textContent = `${count} sample drawing${count === 1 ? '' : 's'}`;
}));
const form = document.querySelector('#contact-form');
if (form) {
  const submit = document.querySelector('#submit-button');
  const status = document.querySelector('#form-status');
  const showStatus = (message, type) => { status.textContent = message; status.className = type; };
  async function prepareForm() {
    submit.disabled = true;
    try {
      const response = await fetch('contact.php?action=token', {cache:'no-store', credentials:'same-origin'});
      const data = await response.json();
      if (!response.ok || !data.token) throw new Error('token');
      document.querySelector('#form-token').value = data.token;
      submit.disabled = false; submit.textContent = 'Send enquiry ↗';
      return true;
    } catch {
      submit.textContent = 'Form unavailable';
      showStatus('The form is currently unavailable. Please contact us by email or phone.', 'error');
      return false;
    }
  }
  prepareForm();
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    submit.disabled = true; submit.textContent = 'Sending…'; showStatus('', '');
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 20000);
    try {
      const response = await fetch(form.action, {method:'POST', body:new FormData(form), credentials:'same-origin', signal:controller.signal});
      const data = await response.json();
      if (!response.ok || !data.success) throw new Error(data.message || 'We could not send your message at this time. Please try again.');
      form.reset(); await prepareForm(); showStatus(data.message, 'success');
    } catch (error) {
      showStatus(error.name === 'AbortError' ? 'The request timed out. Please contact us by email before resubmitting to avoid duplicate enquiries.' : (error.message === 'Failed to fetch' ? 'Connection failed. Please try again or contact us by email.' : error.message), 'error');
    } finally {clearTimeout(timeout); if (document.querySelector('#form-token').value) {submit.disabled = false; submit.textContent = 'Send enquiry ↗';}}
  });
}
async function initializeViewer() {
  const title = document.querySelector('#project-title');
  if (!title) return;
  const status = document.querySelector('#viewer-status');
  try {
    const projects = window.pavanSaiProjects;
    if (!Array.isArray(projects)) throw new Error('catalog');
    const project = projects.find(item => item.id === new URLSearchParams(location.search).get('project'));
    if (!project) {status.textContent = 'Drawing not found. Please select a project from the portfolio.'; return;}
    if (typeof project.file !== 'string' || /[\\/:]/.test(project.file) || !/\.pdf$/i.test(project.file)) {
      throw new Error('Invalid PDF filename');
    }
    // Resolve from this viewer's URL, including sites hosted in a subdirectory.
    const assets = new URL('../assets/', location.href);
    const pdf = new URL('pdfs/' + encodeURIComponent(project.file), assets).href;
    title.textContent = project.title;
    document.title = project.title + ' | Pavan Sai Engineering Services';
    document.querySelector('#project-description').textContent = `${project.description} ${project.pages} page · ${project.size} KB`;
    document.querySelector('#pdf-open').href = pdf;
    document.querySelector('#pdf-download').href = pdf;
    document.querySelector('#pdf-actions').hidden = false;
    const container = document.querySelector('#pdf-container');
    if (location.protocol === 'file:' || matchMedia('(max-width: 760px)').matches) {
      const panel = document.createElement('div'); panel.className = 'mobile-pdf';
      const preview = document.createElement('img'); preview.src = new URL(`images/portfolio/${encodeURIComponent(project.id)}.png`, assets).href; preview.alt = project.title + ' drawing preview';
      const link = document.createElement('a'); link.href = pdf; link.target = '_blank'; link.rel = 'noopener'; link.className = 'button'; link.textContent = 'Open drawing PDF ↗';
      panel.append(preview, link); container.append(panel); status.textContent = 'Your drawing is ready to open.';
    } else {
      status.textContent = 'Loading selected PDF…';
      const frame = document.createElement('iframe'); frame.title = project.title + ' PDF'; frame.src = pdf + '#view=FitH';
      frame.addEventListener('load', () => {status.textContent = 'PDF requested. If the preview is unavailable, open it in a new tab.';});
      container.append(frame);
      setTimeout(() => {if (status.textContent === 'Loading selected PDF…') status.textContent = 'The drawing may still be loading. You can also open the PDF in a new tab.';}, 12000);
    }
  } catch {status.textContent = 'We could not load this drawing. Please return to the portfolio and try again.';}
}
initializeViewer();
