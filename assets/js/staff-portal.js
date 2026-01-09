(function(){
  const portals = document.querySelectorAll('.eh-staff-portal');
  if (!portals.length) return;

  portals.forEach(initPortal);

  function initPortal(root){
    const restUrl = root.dataset.rest || '';
    const viewsUrl = root.dataset.views || '';
    const nonce = root.dataset.nonce || '';
    const events = parseJson(root.dataset.events, []);
    const fieldLabels = parseJson(root.dataset.fields, {});
    const eventSelect = root.querySelector('.eh-sp-event');
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

    let currentData = [];
    let currentFields = Object.keys(fieldLabels);
    let currentEventId = '';
    let savedViews = {};

    if (!restUrl || !eventSelect || !fieldWrap || !tableHead || !tableBody) {
      return;
    }

    // Build event options
    events.forEach(evt => {
      const opt = document.createElement('option');
      opt.value = evt.id;
      opt.textContent = evt.title;
      eventSelect.appendChild(opt);
    });

    // Build field checkboxes
    currentFields.forEach((field) => {
      const label = document.createElement('label');
      label.className = 'eh-sp-field';
      const cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.value = field;
      cb.checked = true;
      label.appendChild(cb);
      label.appendChild(document.createTextNode(' ' + (fieldLabels[field] || field)));
      fieldWrap.appendChild(label);
    });

    function getSelectedFields(){
      const cbs = fieldWrap.querySelectorAll('input[type="checkbox"]:checked');
      const list = Array.from(cbs).map(cb => cb.value);
      return list.length ? list : currentFields;
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
        renderTable(getSelectedFields(), []);
        return;
      }
      setStatus('Laden...', false);
      const fields = getSelectedFields();
      const url = new URL(restUrl);
      url.searchParams.set('session_id', eventId);
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
          renderTable(fields, currentData);
          return;
        }
        currentData = res.registrations || [];
        currentFields = res.fields || fields;
        setStatus('', false);
        renderTable(currentFields, currentData);
      }).catch(() => {
        setStatus('Laden mislukt.', true);
      });
    }

    eventSelect.addEventListener('change', (e) => {
      currentEventId = e.target.value;
      fetchRegistrations(currentEventId);
    });

    fieldWrap.addEventListener('change', () => {
      currentFields = getSelectedFields();
      if (currentEventId) {
        fetchRegistrations(currentEventId);
      } else {
        renderTable(currentFields, currentData);
      }
    });

    if (btnCsv){
      btnCsv.addEventListener('click', (e) => {
        e.preventDefault();
        if (!currentData.length){ setStatus('Geen inschrijvingen om te exporteren.', true); return; }
        downloadCSV(getSelectedFields(), currentData);
      });
    }
    if (btnHtml){
      btnHtml.addEventListener('click', (e) => {
        e.preventDefault();
        if (!currentData.length){ setStatus('Geen inschrijvingen om te tonen.', true); return; }
        openHtml(getSelectedFields(), currentData);
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
      if (currentEventId){
        fetchRegistrations(currentEventId);
      } else {
        renderTable(fields, currentData);
      }
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

    // Auto-load first event if available
    if (eventSelect.value){
      currentEventId = eventSelect.value;
      fetchRegistrations(eventSelect.value);
    } else if (events[0]) {
      eventSelect.value = events[0].id;
      currentEventId = events[0].id;
      fetchRegistrations(events[0].id);
    }

    fetchViews();
  }

  function parseJson(str, fallback){
    try { return JSON.parse(str || ''); } catch(e){ return fallback; }
  }
})();
