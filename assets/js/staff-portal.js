(function(){
  const portals = document.querySelectorAll('.eh-staff-portal');
  if (!portals.length) return;

  portals.forEach(initPortal);

  function initPortal(root){
    const restUrl = root.dataset.rest || '';
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

    let currentData = [];
    let currentFields = Object.keys(fieldLabels);

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
      fetchRegistrations(e.target.value);
    });

    fieldWrap.addEventListener('change', () => {
      renderTable(getSelectedFields(), currentData);
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

    // Auto-load first event if available
    if (eventSelect.value){
      fetchRegistrations(eventSelect.value);
    } else if (events[0]) {
      eventSelect.value = events[0].id;
      fetchRegistrations(events[0].id);
    }
  }

  function parseJson(str, fallback){
    try { return JSON.parse(str || ''); } catch(e){ return fallback; }
  }
})();
