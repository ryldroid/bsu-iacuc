<?php

/** @var array  $user */
/** @var string $csrf */

$title = 'Clearance Pool';
include dirname(__DIR__) . '/includes/header.php';
include dirname(__DIR__) . '/includes/scroll-top.php';

$user = $user ?? $_SESSION['user'] ?? [];
$csrf = $csrf ?? '';
?>

<link rel="stylesheet" href="<?= asset_css('protocol-list.css') ?>">
<link rel="stylesheet" href="<?= asset_css('personnel/personnel-home.css') ?>">
<link rel="stylesheet" href="<?= asset_css('personnel/clearances.css') ?>">

<div class="body">
  <?php include dirname(__DIR__) . '/includes/navigation.php'; ?>

  <main class="main-content" id="main-content" tabindex="-1">

    <div class="dashboard-page-header">
      <div>
        <h1 class="dashboard-page-title">Clearance Pool</h1>
        <p>Drag a screenshot onto its protocol, then confirm. On a touch screen, tap a screenshot then tap its protocol.</p>
      </div>

      <button class="row-btn row-btn-primary" id="confirmBtn" type="button" disabled>
        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#check-icon" />
        </svg>
        Confirm (<span id="stagedCount">0</span>)
      </button>
    </div>

    <div class="clearance-board">
      <section class="clearance-tray" id="clearanceTrayCard">
        <h2>Unsorted Screenshots</h2>
        <p class="helper">Drag-and-drop or tap on an ARC then assign it to an endorsed protocol.</p>
        <div class="clearance-tray-grid" id="trayGrid">
          <p class="helper clearance-empty">Loading&hellip;</p>
        </div>
      </section>

      <section class="clearance-protocols">
        <h2>Endorsed Protocols</h2>
        <div class="clearance-protocol-grid" id="protocolGrid">
          <p class="helper clearance-empty">Loading&hellip;</p>
        </div>
      </section>
    </div>

    <section class="clearance-confirmed">
      <h2>Recently Confirmed</h2>
      <div class="clearance-confirmed-list" id="confirmedList">
        <p class="helper clearance-empty">Nothing confirmed yet.</p>
      </div>
    </section>

  </main>
</div>

<!-- Confirm review modal -->
<div class="modal-backdrop" id="confirmReviewBackdrop">
  <div class="modal-card clearance-modal-card">
    <h2>Confirm Clearances</h2>
    <p class="helper clearance-modal-helper"><strong class="clearance-modal-warning">Double-check all entries below before proceeding.</strong> Once you proceed, each protocol is marked <strong>Approved</strong>.</p>

    <div id="confirmReviewError" class="alert error-messages clearance-modal-error" hidden></div>

    <div class="modal-actions">
      <button class="button" type="button" onclick="closeConfirmReview()">Cancel</button>
      <button class="button btn-apply" type="button" id="confirmProceedBtn" onclick="proceedConfirm()">
        <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
          <use href="#check-icon" />
        </svg>
        Proceed
      </button>
    </div>
  </div>
</div>

<!-- Add/Edit IPN modal -->
<div class="modal-backdrop" id="ipnModalBackdrop">
  <div class="modal-card">
    <h2 id="ipnModalTitle">Add IPN</h2>
    <p class="helper">This is the IPN used to identify this protocol, and will be kept in sync with its entry on the Records page.</p>

    <div id="ipnModalError" class="alert error-messages" hidden></div>

    <div class="clearance-ipn-field">
      <label for="ipnModalInput">IPN</label>
      <input type="text" id="ipnModalInput" placeholder="e.g. BSU-IACUC-2025-001">
    </div>

    <div class="modal-actions">
      <button class="button" type="button" onclick="closeIpnModal()">Cancel</button>
      <button class="button btn-apply" type="button" id="ipnModalSaveBtn" onclick="saveIpn()">Save</button>
    </div>
  </div>
</div>

<!-- Image zoom lightbox -->
<div class="modal-backdrop clearance-zoom-backdrop" id="clearanceZoomBackdrop">
  <div class="clearance-zoom-card">
    <button type="button" class="clearance-zoom-close" onclick="closeZoom()" title="Close">&times;</button>
    <img id="clearanceZoomImg" src="" alt="">
    <p class="clearance-zoom-caption" id="clearanceZoomCaption"></p>
  </div>
</div>

<script>
  const CSRF_TOKEN = <?= json_encode($csrf) ?>;
  const ROOT_URL = <?= json_encode(ROOT) ?>;
  const CLEARANCE_POOL_API = ROOT_URL + '/apply/clearance_pool';
  const CLEARANCE_STAGE_API = ROOT_URL + '/apply/clearance_stage';
  const CLEARANCE_UNSTAGE_API = ROOT_URL + '/apply/clearance_unstage';
  const CLEARANCE_CONFIRM_API = ROOT_URL + '/apply/clearance_confirm';
  const CLEARANCE_UNASSIGN_API = ROOT_URL + '/apply/clearance_unassign';
  const CLEARANCE_DELETE_API = ROOT_URL + '/apply/clearance_delete';
  const CLEARANCE_ASSIGN_IPN_API = ROOT_URL + '/apply/clearance_assign_ipn';

  let boardData = {
    unassigned: [],
    staged: [],
    confirmed: [],
    endorsed_protocols: []
  };

  let selectedPoolId = null;

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;'
    } [ch]));
  }

  function showFlash(message) {
    const existing = document.getElementById('flashSuccess');
    if (existing) existing.remove();
    const flash = document.createElement('div');
    flash.className = 'alert success-message';
    flash.id = 'flashSuccess';
    flash.textContent = message;
    const main = document.getElementById('main-content');
    main.insertBefore(flash, main.firstChild);
    setTimeout(() => flash.remove(), 4000);
  }

  function isImage(name) {
    return /\.(jpe?g|png)$/i.test(name || '');
  }

  function thumbHtml(item, {
    allowDelete = true
  } = {}) {
    const deleteBtn = allowDelete ? `
                <button type="button" class="clearance-delete-btn" title="Delete" aria-label="Delete"
                    onclick="event.stopPropagation(); deletePoolItem(${item.id}, this)">
                    <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#close-icon"/></svg>
                </button>` : '';

    if (!isImage(item.original_name)) {
      return `<div class="clearance-file-icon">
                 <svg width="28" height="28" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#review-icon"/></svg>
                 ${deleteBtn}
               </div>`;
    }
    const safeName = escapeHtml(item.original_name).replace(/'/g, "\\'");
    return `<div class="clearance-thumb-media">
                <img src="${item.file_url}" alt="${escapeHtml(item.original_name)}" loading="lazy">
                <button type="button" class="clearance-zoom-btn" title="Preview Image" aria-label="Preview Image"
                    onclick="openZoom(event, '${item.file_url}', '${safeName}')">
                    <svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#search-icon"/></svg>
                </button>
                ${deleteBtn}
            </div>`;
  }

  // ===== Image zoom =====
  const zoomBackdrop = document.getElementById('clearanceZoomBackdrop');
  const zoomImg = document.getElementById('clearanceZoomImg');
  const zoomCaption = document.getElementById('clearanceZoomCaption');

  function openZoom(e, url, name) {
    e.stopPropagation();
    zoomImg.src = url;
    zoomImg.alt = name;
    zoomCaption.textContent = name;
    zoomBackdrop.classList.add('open');
  }

  function closeZoom() {
    zoomBackdrop.classList.remove('open');
    zoomImg.src = '';
  }

  zoomBackdrop.addEventListener('click', e => {
    if (e.target === zoomBackdrop) closeZoom();
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && zoomBackdrop.classList.contains('open')) closeZoom();
  });

  async function loadBoard() {
    try {
      const res = await fetch(CLEARANCE_POOL_API);
      boardData = await res.json();
    } catch (err) {
      document.getElementById('trayGrid').innerHTML = '<p class="helper clearance-empty">Could not load the pool. Please refresh.</p>';
      return;
    }
    if (selectedPoolId !== null && !(boardData.unassigned || []).some(item => Number(item.id) === selectedPoolId)) {
      selectedPoolId = null;
    }
    renderTray();
    renderProtocols();
    renderConfirmed();
    updateConfirmButton();
  }

  function toggleSelectThumb(poolId) {
    poolId = Number(poolId);
    selectedPoolId = selectedPoolId === poolId ? null : poolId;
    renderTray();
    renderProtocols();
  }

  function renderTray() {
    const grid = document.getElementById('trayGrid');
    if (!boardData.unassigned || boardData.unassigned.length === 0) {
      grid.innerHTML = '<p class="helper clearance-empty">Nothing waiting to be sorted.</p>';
      return;
    }

    grid.innerHTML = boardData.unassigned.map(item => `
            <div class="clearance-thumb${selectedPoolId === Number(item.id) ? ' is-selected' : ''}" draggable="true" data-pool-id="${item.id}"
                ondragstart="onDragStart(event, ${item.id})"
                onclick="toggleSelectThumb(${item.id})">
                ${thumbHtml(item)}
                <span class="clearance-thumb-name">${escapeHtml(item.original_name)}</span>
            </div>
        `).join('');
  }

  function stagedFor(protocolId) {
    return (boardData.staged || []).find(s => Number(s.protocol_id) === Number(protocolId));
  }

  function renderProtocols() {
    const grid = document.getElementById('protocolGrid');
    if (!boardData.endorsed_protocols || boardData.endorsed_protocols.length === 0) {
      grid.innerHTML = '<p class="helper clearance-empty">No endorsed protocols waiting on a clearance right now.</p>';
      return;
    }

    grid.innerHTML = boardData.endorsed_protocols.map(p => {
      const staged = stagedFor(p.protocol_id);
      const already = p.latest_clearance_version_id && !staged;
      const hasIpn = !!p.reference_no;
      const isTarget = selectedPoolId !== null && !already && hasIpn;
      const dragHandlers = hasIpn ?
        `ondragover="event.preventDefault()" ondrop="onDrop(event, ${p.protocol_id})"` :
        '';

      return `
            <div class="clearance-protocol-card${staged ? ' has-staged' : ''}${isTarget ? ' is-target' : ''}${!hasIpn ? ' no-ipn' : ''}"
                data-protocol-id="${p.protocol_id}"
                ${dragHandlers}
                onclick="onCardTap(${p.protocol_id}, ${hasIpn})">
                <div class="clearance-protocol-info">
                    <span class="clearance-protocol-ref">${escapeHtml(p.reference_no)}</span>
                    <span class="clearance-protocol-title">${escapeHtml(p.research_title)}</span>
                    <span class="helper">Researcher: ${escapeHtml(p.first_name)} ${escapeHtml(p.last_name || '')}</span>
                    <span class="clearance-protocol-ipn">
                        IPN: ${hasIpn ? escapeHtml(p.reference_no) : '<em>not yet assigned</em>'}
                        <a href="#" class="clearance-ipn-link" onclick="event.preventDefault(); event.stopPropagation(); openIpnModal(${p.protocol_id}, '${escapeHtml(p.reference_no || '').replace(/'/g, "\\'")}')">${hasIpn ? 'Edit IPN' : 'Add IPN'}</a>
                    </span>
                </div>
                <div class="clearance-drop-zone">
                    ${staged ? `
                        <div class="clearance-thumb clearance-thumb--staged">
                            ${thumbHtml(staged, { allowDelete: false })}
                            <button type="button" class="clearance-unstage-btn" title="Remove"
                                onclick="event.stopPropagation(); unstage(${staged.id})">&times;</button>
                        </div>
                    ` : already ? `
                        <span class="helper">Already has a clearance on file.</span>
                    ` : !hasIpn ? `
                        <span class="helper clearance-drop-hint">Assign an IPN before attaching a clearance</span>
                    ` : `
                        <span class="helper clearance-drop-hint">Drop a screenshot here, or tap it after selecting one</span>
                    `}
                </div>
            </div>`;
    }).join('');
  }

  function renderConfirmed() {
    const list = document.getElementById('confirmedList');
    if (!boardData.confirmed || boardData.confirmed.length === 0) {
      list.innerHTML = '<p class="helper clearance-empty">Nothing confirmed yet.</p>';
      return;
    }

    list.innerHTML = boardData.confirmed.map(item => `
            <div class="clearance-confirmed-row">
                <span class="clearance-confirmed-ref">${escapeHtml(item.reference_no)}</span>
                <span class="clearance-confirmed-title">${escapeHtml(item.protocol_title)}</span>
                <button type="button" class="button" onclick="detach(${item.id}, this)">Detach</button>
            </div>
        `).join('');
  }

  function updateConfirmButton() {
    const count = (boardData.staged || []).length;
    document.getElementById('stagedCount').textContent = count;
    document.getElementById('confirmBtn').disabled = count === 0;
  }

  // ===== Drag and drop, and tap-to-place (for touch screens) =====
  function onDragStart(e, poolId) {
    e.dataTransfer.setData('text/plain', String(poolId));
  }

  function onDrop(e, protocolId) {
    e.preventDefault();
    const poolId = Number(e.dataTransfer.getData('text/plain'));
    if (!poolId) return;
    stageItem(poolId, protocolId);
  }

  function onCardTap(protocolId, hasIpn) {
    if (!hasIpn || selectedPoolId === null) return;
    const poolId = selectedPoolId;
    selectedPoolId = null;
    stageItem(poolId, protocolId);
  }

  async function stageItem(poolId, protocolId) {
    try {
      const res = await fetch(CLEARANCE_STAGE_API, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': CSRF_TOKEN
        },
        body: JSON.stringify({
          pool_id: poolId,
          protocol_id: protocolId
        }),
      });
      const data = await res.json();
      if (!data.success) {
        alert(data.error ?? 'Could not match that screenshot. Refreshing.');
      }
    } catch (err) {
      alert('Network error. Refreshing.');
    }
    loadBoard();
  }

  async function unstage(poolId) {
    try {
      const res = await fetch(CLEARANCE_UNSTAGE_API, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': CSRF_TOKEN
        },
        body: JSON.stringify({
          pool_id: poolId
        }),
      });
      const data = await res.json();
      if (!data.success) alert(data.error ?? 'Could not undo that match.');
    } catch (err) {
      alert('Network error.');
    }
    loadBoard();
  }

  async function deletePoolItem(poolId, btn) {
    const ok = await confirmAction('Delete this screenshot? This cannot be undone.', {
      okText: 'Delete',
      cancelText: 'Cancel',
      danger: true
    });
    if (!ok) return;

    setButtonBusy(btn, true, 'Deleting...');

    try {
      const res = await fetch(CLEARANCE_DELETE_API, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': CSRF_TOKEN
        },
        body: JSON.stringify({
          pool_id: poolId
        }),
      });
      const data = await res.json();
      if (!data.success) alert(data.error ?? 'Could not delete that screenshot.');
    } catch (err) {
      alert('Network error.');
    }
    loadBoard();
  }

  async function detach(poolId, btn) {
    const ok = await confirmAction('Detach this clearance? The protocol will revert to Endorsed and the screenshot goes back to the pool.', {
      okText: 'Detach',
      cancelText: 'Cancel'
    });
    if (!ok) return;

    setButtonBusy(btn, true, 'Detaching...');

    try {
      const res = await fetch(CLEARANCE_UNASSIGN_API, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': CSRF_TOKEN
        },
        body: JSON.stringify({
          pool_id: poolId
        }),
      });
      const data = await res.json();
      if (!data.success) alert(data.error ?? 'Could not detach.');
    } catch (err) {
      alert('Network error.');
    }
    loadBoard();
  }

  // ===== Add/Edit IPN modal =====
  const ipnModalBackdrop = document.getElementById('ipnModalBackdrop');
  let ipnModalProtocolId = null;

  function openIpnModal(protocolId, currentValue) {
    ipnModalProtocolId = protocolId;
    document.getElementById('ipnModalTitle').textContent = currentValue ? 'Edit IPN' : 'Add IPN';
    document.getElementById('ipnModalInput').value = currentValue || '';
    document.getElementById('ipnModalError').hidden = true;
    ipnModalBackdrop.classList.add('open');
    document.getElementById('ipnModalInput').focus();
  }

  function closeIpnModal() {
    ipnModalBackdrop.classList.remove('open');
    ipnModalProtocolId = null;
  }

  ipnModalBackdrop.addEventListener('click', e => {
    if (e.target === ipnModalBackdrop) closeIpnModal();
  });

  async function saveIpn() {
    const input = document.getElementById('ipnModalInput');
    const errBox = document.getElementById('ipnModalError');
    const value = input.value.trim();

    if (!value) {
      errBox.textContent = 'IPN is required.';
      errBox.hidden = false;
      return;
    }

    const btn = document.getElementById('ipnModalSaveBtn');
    setButtonBusy(btn, true, 'Saving...');

    try {
      const res = await fetch(CLEARANCE_ASSIGN_IPN_API, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': CSRF_TOKEN
        },
        body: JSON.stringify({
          protocol_id: ipnModalProtocolId,
          reference_no: value
        }),
      });
      const data = await res.json();
      if (!data.success) {
        errBox.textContent = data.error ?? 'Could not save the IPN.';
        errBox.hidden = false;
        setButtonBusy(btn, false);
        return;
      }
      closeIpnModal();
      loadBoard();
    } catch (err) {
      errBox.textContent = 'Network error. Please try again.';
      errBox.hidden = false;
    }
    setButtonBusy(btn, false);
  }

  // ===== Confirm review dialog =====
  const confirmReviewBackdrop = document.getElementById('confirmReviewBackdrop');

  document.getElementById('confirmBtn').addEventListener('click', () => {
    const staged = boardData.staged || [];
    if (staged.length === 0) return;

    document.getElementById('confirmReviewError').hidden = true;
    confirmReviewBackdrop.classList.add('open');
  });

  function closeConfirmReview() {
    confirmReviewBackdrop.classList.remove('open');
  }

  confirmReviewBackdrop.addEventListener('click', e => {
    if (e.target === confirmReviewBackdrop) closeConfirmReview();
  });

  async function proceedConfirm() {
    const btn = document.getElementById('confirmProceedBtn');
    const errBox = document.getElementById('confirmReviewError');
    setButtonBusy(btn, true, 'Confirming...');

    try {
      const res = await fetch(CLEARANCE_CONFIRM_API, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': CSRF_TOKEN
        },
        body: JSON.stringify({}),
      });
      const data = await res.json();

      if (data.failures && data.failures.length > 0) {
        errBox.textContent = data.failures.join(' ');
        errBox.hidden = false;
      }

      if (data.confirmed > 0) {
        showFlash(`${data.confirmed} protocol(s) approved.`);
      }

      closeConfirmReview();
      loadBoard();
    } catch (err) {
      errBox.textContent = 'Network error. Please try again.';
      errBox.hidden = false;
    }
    setButtonBusy(btn, false);
  }

  loadBoard();
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>