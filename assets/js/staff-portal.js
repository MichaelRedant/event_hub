(function(){
  const portals = document.querySelectorAll('.eh-staff-portal');
  if (!portals.length) return;

  portals.forEach(initPortal);

  function initPortal(root){
    const restUrl = root.dataset.rest || '';
    const viewsUrl = root.dataset.views || '';
    const nonce = root.dataset.nonce || '';
    const events = parseJson(root.dataset.events, []);
    const occurrences = parseJson(root.dataset.occurrences, {});
    const fieldLabels = parseJson(root.dataset.fields, {});
    const eventSelect = root.querySelector('.eh-sp-event');
    const occurrenceSelect = root.querySelector('.eh-sp-occurrence');
    const statusEl = root.querySelector('.eh-sp-status');
    const tableHead = root.querySelector('.eh-sp-table thead');
    const tableBody = root.querySelector('.eh-sp-table tbody');
    const fieldWrap = root.querySelector('.eh-sp-field-list');
    const btnCsv = root.querySelector('.eh-sp-export-csv');
    const btnHtml = root.querySelector('.eh-sp-export-html');
    const viewSelect = root.querySelector('.eh-sp-view-select');
    const viewApply = root.querySelector('.eh-sp-view-apply');
    const viewDelete = root.querySelector('.eh-sp-view-delete');
    const viewName = root.querySelector('.eh-sp-view-name');
    const viewSave = root.querySelector('.eh-sp-view-save-btn');
    const searchInput = root.querySelector('.eh-sp-search');
    const layoutMode = root.dataset.layout || 'table';
    const addCustomBtn = root.querySelector('.eh-sp-add-custom');
    const customNameInput = root.querySelector('.eh-sp-custom-name');

    let currentData = [];
    let currentFields = Object.keys(fieldLabels);
    let currentEventId = '';
    let currentOccurrenceId = '';
    let savedViews = {};
    let filteredData = [];

    const occurrenceAllLabel = occurrenceSelect && occurrenceSelect.querySelector('option')
      ? occurrenceSelect.querySelector('option').textContent
      : 'Alle datums';

    if (!restUrl || !eventSelect || !fieldWrap || !tableHead || !tableBody) {
      return;
    }

    // Build event options (already in PHP, keep in sync if needed)
    if (!eventSelect.options.length && events.length) {
      events.forEach(evt => {
        const opt = document.createElement('option');
        opt.value = evt.id;
        opt.textContent = evt.title;
        eventSelect.appendChild(opt);
      });
    }

    function addFieldOption(field, checked = true, labelText){
      const label = document.createElement('label');
      label.className = 'eh-sp-field';
      label.dataset.field = field;
      const cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.value = field;
      cb.checked = checked;
      const text = document.createElement('span');
      text.textContent = ' ' + (labelText || fieldLabels[field] || field);
      const controls = document.createElement('span');
      controls.className = 'eh-sp-move';
      const up = document.createElement('button');
      up.type = 'button';
      up.className = 'eh-sp-move-up';
      up.textContent = '↑';
      const down = document.createElement('button');
      down.type = 'button';
      down.className = 'eh-sp-move-down';
      down.textContent = '↓';
      controls.appendChild(up);
      controls.appendChild(down);
      label.appendChild(cb);
      label.appendChild(text);
      label.appendChild(controls);
      fieldWrap.appendChild(label);
    }

    // Build field checkboxes
    currentFields.forEach((field) => addFieldOption(field, true));

    function getSelectedFields(){
      const labels = fieldWrap.querySelectorAll('.eh-sp-field');
      const list = [];
      labels.forEach(label => {
        const cb = label.querySelector('input[type="checkbox"]');
        if (cb && cb.checked) {
          list.push(cb.value);
        }
      });
      return list.length ? list : currentFields;
    }

    function updateOccurrenceSelect(eventId){
      if (!occurrenceSelect) return;
      const items = occurrences && eventId ? (occurrences[eventId] || []) : [];
      occurrenceSelect.innerHTML = '';
      const allOpt = document.createElement('option');
      allOpt.value = '';
      allOpt.textContent = occurrenceAllLabel;
      occurrenceSelect.appendChild(allOpt);
      if (!items.length) {
        occurrenceSelect.disabled = true;
        currentOccurrenceId = '';
        return;
      }
      items.forEach((item) => {
        const opt = document.createElement('option');
        opt.value = item.id;
        opt.textContent = item.label || ('#' + item.id);
        occurrenceSelect.appendChild(opt);
      });
      occurrenceSelect.disabled = false;
      currentOccurrenceId = occurrenceSelect.value || '';
    }

    function setStatus(msg, isError){
      if (!statusEl) return;
      statusEl.textContent = msg || '';
      statusEl.className = 'eh-sp-status' + (isError ? ' error' : '');
    }

    function renderTable(fields, data){
      tableHead.innerHTML = '';
      tableBody.innerHTML = '';
      const trHead = document.createElement('tr');
      fields.forEach(f => {
        const th = document.createElement('th');
        th.textContent = fieldLabels[f] || f;
        trHead.appendChild(th);
      });
      tableHead.appendChild(trHead);

      if (!data.length){
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = fields.length || 1;
        td.textContent = 'Geen inschrijvingen gevonden.';
        tr.appendChild(td);
        tableBody.appendChild(tr);
        return;
      }

      data.forEach(row => {
        const tr = document.createElement('tr');
        fields.forEach(f => {
          const td = document.createElement('td');
          let val = row[f];
          if (val === null || val === undefined) { val = ''; }
          td.textContent = typeof val === 'object' ? JSON.stringify(val) : String(val);
          tr.appendChild(td);
        });
        tableBody.appendChild(tr);
      });
    }

    function renderCards(fields, data){
      tableHead.innerHTML = '';
      tableBody.innerHTML = '';
      if (!data.length){
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.textContent = 'Geen inschrijvingen gevonden.';
        tr.appendChild(td);
        tableBody.appendChild(tr);
        return;
      }
      data.forEach(row => {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        const card = document.createElement('div');
        card.className = 'eh-sp-card';
        fields.forEach(f => {
          const item = document.createElement('div');
          item.className = 'eh-sp-card__item';
          const label = document.createElement('div');
          label.className = 'eh-sp-card__label';
          label.textContent = fieldLabels[f] || f;
          const valEl = document.createElement('div');
          valEl.className = 'eh-sp-card__value';
          let val = row[f];
          if (val === null || val === undefined) { val = ''; }
          valEl.textContent = typeof val === 'object' ? JSON.stringify(val) : String(val);
          item.appendChild(label);
          item.appendChild(valEl);
          card.appendChild(item);
        });
        td.appendChild(card);
        tr.appendChild(td);
        tableBody.appendChild(tr);
      });
    }

    function render(fields, data){
      if (layoutMode === 'cards') {
        renderCards(fields, data);
      } else {
        renderTable(fields, data);
      }
    }

    function toCSV(fields, data){
      const rows = [];
      rows.push(fields.map(f => fieldLabels[f] || f));
      data.forEach(row => {
        rows.push(fields.map(f => safeCell(row[f])));
      });
      return rows.map(r => r.map(csvEscape).join(',')).join('\n');
    }

    function csvEscape(value){
      const v = value == null ? '' : String(value);
      if (/[",\n]/.test(v)) {
        return '"' + v.replace(/"/g, '""') + '"';
      }
      return v;
    }

    function safeCell(val){
      if (val === null || val === undefined) return '';
      if (Array.isArray(val)) return val.join('; ');
      if (typeof val === 'object') return Object.entries(val).map(([k,v]) => k+': '+v).join(' | ');
      return val;
    }

    function downloadCSV(fields, data){
      const csv = toCSV(fields, data);
      const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'registraties-event.csv';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    }

    function openHtml(fields, data){
      const win = window.open('', '_blank');
      if (!win) return;
      const headCells = fields.map(f => '<th>'+escapeHtml(fieldLabels[f] || f)+'</th>').join('');
      const bodyRows = data.map(row => {
        const cells = fields.map(f => '<td>'+escapeHtml(safeCell(row[f]))+'</td>').join('');
        return '<tr>'+cells+'</tr>';
      }).join('');
      const html = '<!doctype html><html><head><meta charset="utf-8"><title>Registraties</title><style>body{font-family:Arial, sans-serif;padding:16px;}table{border-collapse:collapse;width:100%;}th,td{border:1px solid #ddd;padding:8px;text-align:left;}th{background:#f5f5f5;}</style></head><body><h2>Registraties</h2><table><thead><tr>'+headCells+'</tr></thead><tbody>'+bodyRows+'</tbody></table></body></html>';
      win.document.write(html);
      win.document.close();
    }

    function escapeHtml(val){
      return String(val === undefined || val === null ? '' : val)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;');
    }

    function fetchRegistrations(eventId){
      if (!eventId){
        setStatus('Kies een event om inschrijvingen te laden.', false);
        currentData = [];
        filteredData = [];
        render(getSelectedFields(), []);
        return;
      }
      setStatus('Laden...', false);
      const fields = getSelectedFields();
      const url = new URL(restUrl);
      url.searchParams.set('session_id', eventId);
      if (currentOccurrenceId) {
        url.searchParams.set('occurrence_id', currentOccurrenceId);
      }
      fields.forEach(f => url.searchParams.append('fields[]', f));

      fetch(url.toString(), {
        credentials: 'same-origin',
        headers: {
          'X-WP-Nonce': nonce
        }
      }).then(res => res.json()).then(res => {
        if (!res.success) {
          setStatus(res.message || 'Laden mislukt.', true);
          currentData = [];
          filteredData = [];
          render(fields, []);
          return;
        }
        currentData = res.registrations || [];
        currentFields = res.fields || fields;
        filteredData = currentData;
        setStatus('', false);
        applySearch();
      }).catch(() => {
        setStatus('Laden mislukt.', true);
      });
    }

    eventSelect.addEventListener('change', (e) => {
      currentEventId = e.target.value;
      updateOccurrenceSelect(currentEventId);
      fetchRegistrations(currentEventId);
    });

    if (occurrenceSelect){
      occurrenceSelect.addEventListener('change', (e) => {
        currentOccurrenceId = e.target.value || '';
        if (currentEventId) {
          fetchRegistrations(currentEventId);
        }
      });
    }

    fieldWrap.addEventListener('change', () => {
      currentFields = getSelectedFields();
      applySearch();
    });

    fieldWrap.addEventListener('click', (e) => {
      const target = e.target;
      if (!(target instanceof HTMLElement)) return;
      if (target.classList.contains('eh-sp-move-up') || target.classList.contains('eh-sp-move-down')) {
        const label = target.closest('.eh-sp-field');
        if (!label || !label.parentElement) return;
        const parent = label.parentElement;
        if (target.classList.contains('eh-sp-move-up')) {
          const prev = label.previousElementSibling;
          if (prev) parent.insertBefore(label, prev);
        } else {
          const next = label.nextElementSibling;
          if (next) parent.insertBefore(next, label);
        }
        currentFields = getSelectedFields();
        applySearch();
      }
    });

    if (addCustomBtn) {
      addCustomBtn.addEventListener('click', (e) => {
        e.preventDefault();
        const name = customNameInput ? customNameInput.value.trim() : '';
        if (!name) {
          setStatus('Naam voor kolom ontbreekt.', true);
          return;
        }
        const id = 'custom_' + Date.now();
        fieldLabels[id] = name;
        addFieldOption(id, true, name);
        if (customNameInput) customNameInput.value = '';
        currentFields = getSelectedFields();
        applySearch();
      });
    }

    if (btnCsv){
      btnCsv.addEventListener('click', (e) => {
        e.preventDefault();
        const data = filteredData.length ? filteredData : currentData;
        if (!data.length){ setStatus('Geen inschrijvingen om te exporteren.', true); return; }
        downloadCSV(getSelectedFields(), data);
      });
    }
    if (btnHtml){
      btnHtml.addEventListener('click', (e) => {
        e.preventDefault();
        const data = filteredData.length ? filteredData : currentData;
        if (!data.length){ setStatus('Geen inschrijvingen om te tonen.', true); return; }
        openHtml(getSelectedFields(), data);
      });
    }

    function renderViewsSelect(){
      if (!viewSelect) return;
      viewSelect.innerHTML = '<option value=\"\">Kies een view</option>';
      Object.keys(savedViews).forEach((name) => {
        const opt = document.createElement('option');
        opt.value = name;
        opt.textContent = name;
        viewSelect.appendChild(opt);
      });
    }

    function fetchViews(){
      if (!viewsUrl || !viewSelect) return;
      fetch(viewsUrl, {
        credentials: 'same-origin',
        headers: {'X-WP-Nonce': nonce}
      }).then(res => res.json()).then(res => {
        if (res.success && res.views){
          savedViews = res.views;
          renderViewsSelect();
        }
      }).catch(() => {});
    }

    function applyView(name){
      if (!name || !savedViews[name]) return;
      const fields = savedViews[name];
      fieldWrap.querySelectorAll('input[type=\"checkbox\"]').forEach(cb => {
        cb.checked = fields.includes(cb.value);
      });
      currentFields = fields;
      applySearch();
    }

    if (viewApply && viewSelect){
      viewApply.addEventListener('click', (e) => {
        e.preventDefault();
        applyView(viewSelect.value);
      });
    }

    if (viewDelete && viewSelect){
      viewDelete.addEventListener('click', (e) => {
        e.preventDefault();
        const name = viewSelect.value;
        if (!name || !viewsUrl) return;
        fetch(viewsUrl + '?name=' + encodeURIComponent(name), {
          method: 'DELETE',
          credentials: 'same-origin',
          headers: {'X-WP-Nonce': nonce}
        }).then(res => res.json()).then(res => {
          if (res.success && res.views){
            savedViews = res.views;
            renderViewsSelect();
            setStatus('View verwijderd.', false);
          } else {
            setStatus(res.message || 'Verwijderen mislukt.', true);
          }
        }).catch(() => setStatus('Verwijderen mislukt.', true));
      });
    }

    if (viewSave){
      viewSave.addEventListener('click', (e) => {
        e.preventDefault();
        if (!viewsUrl) return;
        const name = viewName ? viewName.value.trim() : '';
        if (!name){ setStatus('Naam voor view ontbreekt.', true); return; }
        const payload = {name: name, fields: getSelectedFields()};
        fetch(viewsUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': nonce
          },
          body: JSON.stringify(payload)
        }).then(res => res.json()).then(res => {
          if (res.success && res.views){
            savedViews = res.views;
            renderViewsSelect();
            if (viewSelect) viewSelect.value = name;
            setStatus('View opgeslagen.', false);
          } else {
            setStatus(res.message || 'Opslaan mislukt.', true);
          }
        }).catch(() => setStatus('Opslaan mislukt.', true));
      });
    }

    function applySearch(){
      const term = (searchInput ? searchInput.value : '').toLowerCase().trim();
      if (!term){
        filteredData = currentData;
        render(currentFields, filteredData);
        return;
      }
      filteredData = currentData.filter(row => {
        return currentFields.some(f => {
          const val = row[f];
          if (val === null || val === undefined) return false;
          return String(typeof val === 'object' ? JSON.stringify(val) : val).toLowerCase().includes(term);
        });
      });
      render(currentFields, filteredData);
    }

    if (searchInput){
      searchInput.addEventListener('input', applySearch);
    }

    // Auto-load first event if available
    if (eventSelect.value){
      currentEventId = eventSelect.value;
      updateOccurrenceSelect(currentEventId);
      fetchRegistrations(eventSelect.value);
    } else if (events[0]) {
      eventSelect.value = events[0].id;
      currentEventId = events[0].id;
      updateOccurrenceSelect(currentEventId);
      fetchRegistrations(events[0].id);
    }

    fetchViews();
  }

  function parseJson(str, fallback){
    try { return JSON.parse(str || ''); } catch(e){ return fallback; }
  }
})(); 
