/* ============================
   Runtime config (CSP-safe meta tags; no inline scripts)
   ============================ */
const META = (name) => document.querySelector(`meta[name="${name}"]`)?.content || '';
window.API_BASE = META('api-base');
const PUSHER_KEY = META('pusher-key');
const PUSHER_CLUSTER = META('pusher-cluster');

/* ============================
   State
   ============================ */
const state = {
  token: localStorage.getItem('token') || null,
  user: JSON.parse(localStorage.getItem('user') || 'null'),
  hazards: [],
  map: null,
  markers: [],
  currentHazardId: null,
  pusher: null,
  pusherChannel: null,
  chatPollTimer: null,
  chartInstances: {},
};

/* ============================
   Helpers
   ============================ */
function apiUrl(path) {
  return window.API_BASE + 'api' + path;
}

async function api(method, path, body = null, isFormData = false) {
  const headers = {};
  if (state.token) headers['Authorization'] = `Bearer ${state.token}`;
  if (!isFormData) headers['Content-Type'] = 'application/json';
  headers['Accept'] = 'application/json';

  const opts = { method, headers };
  if (body) opts.body = isFormData ? body : JSON.stringify(body);

  const res = await fetch(apiUrl(path), opts);
  let data = null;
  if (res.status !== 204) {
    try {
      data = await res.json();
    } catch (e) {
      throw new Error('Server returned invalid response (check API URL or server logs).');
    }
  }

  if (!res.ok && data) {
    const msg = data.message
      || (data.errors ? Object.values(data.errors).flat().join(', ') : null)
      || 'Request failed (' + res.status + ')';
    throw new Error(msg);
  }

  return data;
}

/**
 * HTML-escape for BOTH text nodes and attribute contexts. Every piece of
 * server- or user-controlled data MUST pass through this before being placed
 * in an innerHTML/bindPopup template (stored-XSS defence).
 */
function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

/** Coerce to a safe integer for id attributes/handlers. */
function toId(value) {
  const n = Number.parseInt(value, 10);
  return Number.isFinite(n) ? n : 0;
}

function showAlert(id, message, type = 'error') {
  const el = document.getElementById(id);
  if (!el) return;
  el.className = 'alert alert-' + type;
  el.textContent = message;
  el.style.display = 'block';
}

function hideAlert(id) {
  const el = document.getElementById(id);
  if (el) { el.style.display = 'none'; el.textContent = ''; }
}

const SECTION_TITLES = {
  splashSection: 'Road Hazard Reporter',
  loginSection: 'Sign In — RoadHaz',
  registerSection: 'Create Account — RoadHaz',
  mapSection: 'Map — RoadHaz',
  reportSection: 'Report a Hazard — RoadHaz',
  detailSection: 'Hazard Detail — RoadHaz',
  adminSection: 'Admin Dashboard — RoadHaz',
};

function showSection(id) {
  unsubscribeFromChat();

  document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
  const section = document.getElementById(id);
  if (section) {
    section.classList.add('active');
    // SPA focus management: move focus to the new view and announce it.
    section.focus({ preventScroll: true });
  }
  if (SECTION_TITLES[id]) document.title = SECTION_TITLES[id];

  document.querySelectorAll('.nav-item').forEach(n => {
    const active = n.dataset.section === id;
    n.classList.toggle('active', active);
    n.setAttribute('aria-current', active ? 'page' : 'false');
  });

  if (id === 'mapSection') {
    setTimeout(() => {
      initMap();
      if (state.map) {
        state.map.invalidateSize();
        loadHazards();
      }
    }, 150);
  }
  if (id === 'adminSection' && state.user?.role === 'admin') loadAdminDashboard();
  if (id === 'detailSection' && state.currentHazardId) loadHazardDetail(state.currentHazardId);
}

function showError(message) {
  showAlert('reportAlert', message);
  showAlert('loginAlert', message);
  showAlert('registerAlert', message);
}

/* ============================
   Theme
   ============================ */
function getPreferredTheme() {
  const stored = localStorage.getItem('theme');
  if (stored) return stored;
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function applyTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  localStorage.setItem('theme', theme);
  const toggle = document.getElementById('themeToggle');
  if (toggle) {
    toggle.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
    toggle.innerHTML = theme === 'dark'
      ? '<span aria-hidden="true">🌙</span>'
      : '<span aria-hidden="true">☀️</span>';
  }
}

function toggleTheme() {
  const current = document.documentElement.getAttribute('data-theme');
  applyTheme(current === 'dark' ? 'light' : 'dark');
}

applyTheme(getPreferredTheme());

/* ============================
   Event delegation (CSP: no inline handlers)
   ============================ */
document.addEventListener('click', (e) => {
  const el = e.target.closest('[data-action]');
  if (!el) return;

  switch (el.dataset.action) {
    case 'logout': logout(); break;
    case 'toggle-theme': toggleTheme(); break;
    case 'show-section': showSection(el.dataset.section); break;
    case 'locate-me': locateMe(); break;
    case 'refresh-map': refreshMap(); break;
    case 'get-location': getLocation(); break;
    case 'go-back': goBack(); break;
    case 'send-chat': sendChat(); break;
    case 'show-detail': showHazardDetail(toId(el.dataset.id)); break;
    case 'switch-tab': switchAdminTab(el.dataset.tab); break;
    case 'toggle-role': toggleUserRole(toId(el.dataset.id), el.dataset.role); break;
    case 'suspend-user': {
      const id = toId(el.dataset.id);
      if (el.dataset.armed === '1') {
        el.dataset.armed = '0';
        suspendUser(id);
      } else {
        el.dataset.armed = '1';
        el.textContent = 'Confirm suspend?';
        el.classList.add('btn-danger');
        setTimeout(() => {
          if (el.dataset.armed === '1') {
            el.dataset.armed = '0';
            el.textContent = 'Suspend';
            el.classList.remove('btn-danger');
          }
        }, 4000);
      }
      break;
    }
  }
});

document.addEventListener('change', (e) => {
  const el = e.target.closest('[data-action="update-status"]');
  if (!el) return;
  updateHazardStatus(toId(el.dataset.id), el.value);
});

// Keyboard activation for hazard list rows (role=button divs).
document.addEventListener('keydown', (e) => {
  if (e.key !== 'Enter' && e.key !== ' ') return;
  const el = e.target.closest('.hazard-item[role="button"][data-action="show-detail"]');
  if (!el) return;
  e.preventDefault();
  showHazardDetail(toId(el.dataset.id));
});

/* ============================
   Auth
   ============================ */
function updateUI() {
  const navUser = document.getElementById('navUser');
  const btnLogout = document.getElementById('btnLogout');
  const bottomNav = document.getElementById('bottomNav');
  const adminNav = document.querySelector('.nav-item[data-section="adminSection"]');

  if (state.user) {
    navUser.textContent = state.user.name;
    navUser.classList.remove('hidden');
    btnLogout.classList.remove('hidden');
    bottomNav.classList.add('visible');
    if (state.user.role === 'admin') {
      adminNav.classList.remove('hidden');
    } else {
      adminNav.classList.add('hidden');
    }
    showSection('mapSection');
  } else {
    navUser.classList.add('hidden');
    btnLogout.classList.add('hidden');
    bottomNav.classList.remove('visible');
    showSection('loginSection');
  }
}

document.getElementById('loginForm').addEventListener('submit', handleLogin);
document.getElementById('registerForm').addEventListener('submit', handleRegister);
document.getElementById('reportForm').addEventListener('submit', handleReport);

async function handleLogin(e) {
  e.preventDefault();
  hideAlert('loginAlert');
  const btn = document.getElementById('loginBtn');
  btn.disabled = true; btn.textContent = 'Signing in...';

  try {
    const data = await api('POST', '/login', {
      email: document.getElementById('loginEmail').value,
      password: document.getElementById('loginPassword').value,
    });
    state.token = data.token;
    state.user = data.user;
    localStorage.setItem('token', data.token);
    localStorage.setItem('user', JSON.stringify(data.user));
    updateUI();
  } catch (err) {
    showAlert('loginAlert', err.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Sign In';
  }
}

async function handleRegister(e) {
  e.preventDefault();
  hideAlert('registerAlert');
  const btn = document.getElementById('regBtn');
  btn.disabled = true; btn.textContent = 'Creating account...';

  try {
    const data = await api('POST', '/register', {
      name: document.getElementById('regName').value,
      email: document.getElementById('regEmail').value,
      password: document.getElementById('regPassword').value,
    });
    state.token = data.token;
    state.user = data.user;
    localStorage.setItem('token', data.token);
    localStorage.setItem('user', JSON.stringify(data.user));
    updateUI();
  } catch (err) {
    showAlert('registerAlert', err.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Create Account';
  }
}

function logout() {
  if (state.token) api('POST', '/logout').catch(() => {});
  stopLiveTracking();
  state.token = null;
  state.user = null;
  localStorage.removeItem('token');
  localStorage.removeItem('user');
  if (state.map) { state.map.remove(); state.map = null; }
  state.markers = [];
  updateUI();
}

/* ============================
   GPS Location — report form
   ============================ */
function getLocation() {
  const btn = document.getElementById('locBtn');
  btn.disabled = true; btn.textContent = '📍 Getting location...';

  if (!navigator.geolocation) {
    showAlert('reportAlert', 'GPS is not available on this device.');
    btn.disabled = false; btn.textContent = '📍 Get Location';
    return;
  }

  navigator.geolocation.getCurrentPosition(
    (pos) => {
      const lat = pos.coords.latitude;
      const lng = pos.coords.longitude;
      document.getElementById('reportLat').value = lat;
      document.getElementById('reportLng').value = lng;
      document.getElementById('coordsText').innerHTML =
        `<strong>Lat:</strong> ${escapeHtml(lat.toFixed(8))}, <strong>Lng:</strong> ${escapeHtml(lng.toFixed(8))}`;
      btn.textContent = '📍 Captured ✓';
      btn.disabled = false;
      btn.style.borderColor = 'var(--success)';
    },
    (err) => {
      let msg = 'Could not get location. ';
      switch (err.code) {
        case err.PERMISSION_DENIED: msg += 'GPS permission denied. Enable location in your browser settings.'; break;
        case err.POSITION_UNAVAILABLE: msg += 'GPS signal unavailable. Try moving to an open area.'; break;
        case err.TIMEOUT: msg += 'GPS request timed out. Try again.'; break;
        default: msg += err.message;
      }
      showAlert('reportAlert', msg);
      btn.textContent = '📍 Get Location';
      btn.disabled = false;
    },
    { enableHighAccuracy: true, timeout: 30000, maximumAge: 0 }
  );
}

/* ============================
   Live location tracking (map)
   ============================ */
let watchId = null;
let userMarker = null;

function locateMe() {
  const btn = document.getElementById('locateMeBtn');
  hideAlert('mapAlert');
  if (!navigator.geolocation) {
    showAlert('mapAlert', 'GPS is not available on this device.');
    return;
  }

  btn.disabled = true;
  btn.textContent = '⏳ Locating...';

  navigator.geolocation.getCurrentPosition(
    (pos) => {
      const { latitude, longitude } = pos.coords;
      if (state.map) {
        state.map.setView([latitude, longitude], 15);
        placeUserMarker(latitude, longitude);
      }
      btn.textContent = '📍 Located ✓';
      btn.disabled = false;
      startLiveTracking();
    },
    (err) => {
      let msg = '';
      switch (err.code) {
        case err.PERMISSION_DENIED: msg = 'GPS permission denied.'; break;
        case err.POSITION_UNAVAILABLE: msg = 'GPS signal unavailable.'; break;
        case err.TIMEOUT: msg = 'GPS request timed out.'; break;
        default: msg = err.message;
      }
      showAlert('mapAlert', 'Could not get location: ' + msg);
      btn.textContent = '📍 Locate Me';
      btn.disabled = false;
    },
    { enableHighAccuracy: true, timeout: 15000, maximumAge: 5000 }
  );
}

function startLiveTracking() {
  if (watchId !== null) return;
  if (!navigator.geolocation) return;

  watchId = navigator.geolocation.watchPosition(
    (pos) => {
      const { latitude, longitude } = pos.coords;
      if (state.map) {
        placeUserMarker(latitude, longitude);
      }
    },
    () => {},
    { enableHighAccuracy: true, maximumAge: 10000, timeout: 30000 }
  );
}

function stopLiveTracking() {
  if (watchId !== null) {
    navigator.geolocation.clearWatch(watchId);
    watchId = null;
  }
  if (userMarker) {
    state.map?.removeLayer(userMarker);
    userMarker = null;
  }
}

function placeUserMarker(lat, lng) {
  if (userMarker) {
    state.map.removeLayer(userMarker);
  }

  const icon = L.divIcon({
    className: '',
    html: '<div class="user-location-pulse"></div><div class="user-location-dot"></div>',
    iconSize: [36, 36],
    iconAnchor: [18, 18],
  });

  userMarker = L.marker([lat, lng], { icon, zIndexOffset: 1000 }).addTo(state.map);
  userMarker.bindPopup('You are here');
}

function refreshMap() {
  stopLiveTracking();
  loadHazards();
}

/* ============================
   Map
   ============================ */
const TYPE_COLORS = {
  pothole: '#d97706',
  fallen_tree: '#059669',
  flooding: '#2563eb',
  broken_traffic_light: '#db2777',
  road_damage: '#4f46e5',
  landslide: '#ea580c',
  manhole_cover_missing: '#7c3aed',
  road_accident: '#dc2626',
};

function getColor(type) {
  return TYPE_COLORS[type] || '#6b7280';
}

function initMap() {
  const container = document.getElementById('map');
  if (!container) return;
  if (state.map) { state.map.invalidateSize(); return; }

  state.map = L.map('map', { zoomControl: true }).setView([14.5995, 120.9842], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>',
  }).addTo(state.map);

  loadHazards();
}

function clearMarkers() {
  state.markers.forEach(m => state.map?.removeLayer(m));
  state.markers = [];
}

function addMarker(hazard) {
  if (!state.map) return;
  const color = getColor(hazard.type);
  const marker = L.circleMarker([hazard.lat, hazard.lng], {
    radius: 10, fillColor: color, color: '#fff',
    weight: 2, opacity: 1, fillOpacity: 0.8,
  }).addTo(state.map);

  const date = new Date(hazard.created_at).toLocaleDateString();
  const id = toId(hazard.id);
  const typeLabel = escapeHtml(String(hazard.type).replace(/_/g, ' '));
  const statusLabel = escapeHtml(hazard.status);
  const desc = escapeHtml(String(hazard.description).substring(0, 100))
    + (String(hazard.description).length > 100 ? '...' : '');
  const reporter = escapeHtml(hazard.user?.name || 'Unknown');

  marker.bindPopup(`
    <strong style="text-transform:capitalize">${typeLabel}</strong>
    <span style="font-size:11px;margin-left:6px;text-transform:uppercase;color:${color};font-weight:600">${statusLabel}</span>
    <p style="margin:4px 0;font-size:13px">${desc}</p>
    <small>Reported by ${reporter} on ${escapeHtml(date)}</small>
    <br><button type="button" data-action="show-detail" data-id="${id}" style="margin-top:4px;padding:4px 12px;border:none;border-radius:4px;background:#2563eb;color:#fff;cursor:pointer;font-size:12px">View &amp; Chat</button>
  `);

  marker.on('click', () => state.currentHazardId = hazard.id);
  state.markers.push(marker);
}

async function loadHazards() {
  try {
    state.hazards = await api('GET', '/hazards?per_page=100');
    const items = state.hazards.data || state.hazards;
    clearMarkers();
    items.forEach(addMarker);
    renderHazardList(items);
  } catch (err) {
    console.error('Failed to load hazards:', err);
  }
}

function renderHazardList(hazards) {
  const container = document.getElementById('hazardList');
  if (!container) return;
  if (!hazards || hazards.length === 0) {
    container.innerHTML = '<div class="empty-state"><div class="icon" aria-hidden="true">🗺️</div><p>No hazards reported yet</p></div>';
    return;
  }
  container.innerHTML = hazards.map(h => {
    const date = new Date(h.created_at).toLocaleDateString();
    const id = toId(h.id);
    return `
      <div class="hazard-item" role="button" tabindex="0" data-action="show-detail" data-id="${id}"
           aria-label="Hazard: ${escapeHtml(String(h.type).replace(/_/g, ' '))}, status ${escapeHtml(h.status)}, reported by ${escapeHtml(h.user?.name || 'Unknown')}">
        <div class="hazard-header">
          <span class="hazard-type ${escapeHtml(h.type)}">${escapeHtml(String(h.type).replace(/_/g, ' '))}</span>
          <span class="hazard-status ${escapeHtml(h.status)}">${escapeHtml(h.status)}</span>
        </div>
        <div class="hazard-desc">${escapeHtml(h.description)}</div>
        <div class="hazard-meta">${escapeHtml(h.user?.name || 'Unknown')} · ${escapeHtml(date)}</div>
      </div>
    `;
  }).join('');
}

/* ============================
   Hazard Report
   ============================ */
async function handleReport(e) {
  e.preventDefault();
  hideAlert('reportAlert');

  if (!state.token) {
    showAlert('reportAlert', 'You must be logged in to report a hazard.');
    return;
  }

  const lat = document.getElementById('reportLat').value;
  const lng = document.getElementById('reportLng').value;
  if (!lat || !lng) {
    showAlert('reportAlert', 'Please get your location or enter coordinates manually.');
    return;
  }

  const btn = document.getElementById('reportBtn');
  btn.disabled = true; btn.textContent = 'Submitting...';

  try {
    const formData = new FormData();
    formData.append('type', document.getElementById('reportType').value);
    formData.append('description', document.getElementById('reportDesc').value);
    formData.append('lat', lat);
    formData.append('lng', lng);

    const fileInput = document.getElementById('reportImage');
    if (fileInput.files[0]) formData.append('image', fileInput.files[0]);

    await api('POST', '/hazards', formData, true);
    showAlert('reportAlert', 'Hazard reported successfully!', 'success');
    document.getElementById('reportForm').reset();
    document.getElementById('coordsText').textContent = 'Tap "Get Location" to capture your position';
    document.getElementById('locBtn').style.borderColor = '';
    loadHazards();
  } catch (err) {
    showAlert('reportAlert', err.message);
  } finally {
    btn.disabled = false; btn.textContent = 'Submit Report';
  }
}

/* ============================
   Real-time Chat (Pusher + polling fallback)
   ============================ */
function connectPusher() {
  if (state.pusher || !window.Pusher) return;
  if (!PUSHER_KEY || PUSHER_KEY === '') return;

  Pusher.logToConsole = false;
  state.pusher = new Pusher(PUSHER_KEY, {
    cluster: PUSHER_CLUSTER,
    authEndpoint: apiUrl('/broadcasting/auth'),
    auth: {
      headers: {
        Authorization: 'Bearer ' + state.token,
        Accept: 'application/json',
      },
    },
  });
}

function subscribeToChat(hazardId) {
  unsubscribeFromChat();

  if (state.pusher) {
    const channelName = 'private-hazard.' + hazardId;
    state.pusherChannel = state.pusher.subscribe(channelName);
    state.pusherChannel.bind('MessageSent', function (data) {
      if (state.currentHazardId === hazardId) {
        appendMessage(data);
      }
    });
    return;
  }

  state.chatPollTimer = setInterval(() => {
    if (state.currentHazardId) loadChat(state.currentHazardId);
  }, 5000);
}

function unsubscribeFromChat() {
  if (state.pusherChannel) {
    state.pusherChannel.unbind();
    state.pusher.unsubscribe(state.pusherChannel.name);
    state.pusherChannel = null;
  }
  if (state.chatPollTimer) {
    clearInterval(state.chatPollTimer);
    state.chatPollTimer = null;
  }
}

function appendMessage(data) {
  const container = document.getElementById('chatMessages');
  if (!container) return;

  const emptyState = container.querySelector('.empty-state');
  if (emptyState) container.innerHTML = '';

  const isAdmin = data.is_from_admin;
  const name = isAdmin ? (data.admin?.name || 'Admin') : (data.user?.name || 'User');
  const date = new Date(data.created_at).toLocaleString();
  const div = document.createElement('div');
  div.className = 'chat-msg ' + (isAdmin ? 'admin' : 'user');
  div.innerHTML = '<div>' + escapeHtml(data.message) + '</div><div class="chat-msg-meta">' + escapeHtml(name) + ' · ' + escapeHtml(date) + '</div>';
  container.appendChild(div);
  container.scrollTop = container.scrollHeight;
}

/* ============================
   Hazard Detail & Chat
   ============================ */
function goBack() {
  state.currentHazardId = null;
  unsubscribeFromChat();
  showSection('mapSection');
}

async function showHazardDetail(id) {
  state.currentHazardId = id;
  showSection('detailSection');
}

async function loadHazardDetail(id) {
  try {
    const hazard = await api('GET', `/hazards/${toId(id)}`);
    const typeLabel = escapeHtml(String(hazard.type).replace(/_/g, ' '));
    document.getElementById('detailContent').innerHTML = `
      <div class="hazard-header">
        <span class="hazard-type ${escapeHtml(hazard.type)}">${typeLabel}</span>
        <span class="hazard-status ${escapeHtml(hazard.status)}">${escapeHtml(hazard.status)}</span>
      </div>
      <p style="margin:8px 0">${escapeHtml(hazard.description)}</p>
      <div class="hazard-meta mb-2">
        <strong>Location:</strong> ${escapeHtml(hazard.lat)}, ${escapeHtml(hazard.lng)}<br>
        <strong>Reported by:</strong> ${escapeHtml(hazard.user?.name || 'Unknown')}
      </div>
    `;

    await loadChat(id);
    connectPusher();
    subscribeToChat(id);
  } catch (err) {
    document.getElementById('detailContent').innerHTML = `<div class="alert alert-error">${escapeHtml(err.message)}</div>`;
    document.getElementById('chatMessages').innerHTML = '<div class="empty-state" style="padding:20px"><p>Unable to load chat. Please try again.</p></div>';
  }
}

async function loadChat(hazardId) {
  try {
    const messages = await api('GET', `/hazards/${toId(hazardId)}/messages`);
    renderMessages(messages);
  } catch (err) {
    document.getElementById('chatMessages').innerHTML = `<div class="alert alert-error">${escapeHtml(err.message)}</div>`;
  }
}

function renderMessages(messages) {
  const container = document.getElementById('chatMessages');
  if (!messages || messages.length === 0) {
    container.innerHTML = '<div class="empty-state" style="padding:20px"><p>No messages yet. Start a conversation with admin.</p></div>';
    return;
  }
  container.innerHTML = messages.reverse().map(m => {
    const isAdmin = m.is_from_admin;
    const name = isAdmin ? (m.admin?.name || 'Admin') : (m.user?.name || 'User');
    const date = new Date(m.created_at).toLocaleString();
    return `
      <div class="chat-msg ${isAdmin ? 'admin' : 'user'}">
        <div>${escapeHtml(m.message)}</div>
        <div class="chat-msg-meta">${escapeHtml(name)} · ${escapeHtml(date)}</div>
      </div>
    `;
  }).join('');
  container.scrollTop = container.scrollHeight;
}

async function sendChat() {
  const input = document.getElementById('chatInput');
  const btn = document.getElementById('chatBtn');
  const msg = input.value.trim();
  if (!msg || !state.currentHazardId) return;

  btn.disabled = true;
  try {
    await api('POST', `/hazards/${toId(state.currentHazardId)}/messages`, { message: msg });
    input.value = '';
    loadChat(state.currentHazardId);
  } catch (err) {
    showError(err.message);
  } finally {
    btn.disabled = false;
  }
}

document.getElementById('chatInput').addEventListener('keydown', (e) => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendChat(); }
});

/* ============================
   Admin Dashboard
   ============================ */
let chartOverTime = null;
let chartByType = null;
let chartByStatus = null;

async function loadAdminDashboard() {
  try {
    const data = await api('GET', '/admin/dashboard');

    const grid = document.getElementById('statsGrid');
    grid.innerHTML = `
      <div class="stat-card"><div class="stat-value">${escapeHtml(data.totals.total_hazards)}</div><div class="stat-label">Total Reports</div></div>
      <div class="stat-card"><div class="stat-value">${escapeHtml(data.totals.pending)}</div><div class="stat-label">Pending</div></div>
      <div class="stat-card"><div class="stat-value">${escapeHtml(data.totals.confirmed)}</div><div class="stat-label">Confirmed</div></div>
      <div class="stat-card"><div class="stat-value">${escapeHtml(data.totals.resolved)}</div><div class="stat-label">Resolved</div></div>
      <div class="stat-card"><div class="stat-value">${escapeHtml(data.totals.total_users)}</div><div class="stat-label">Users</div></div>
    `;

    renderChartOverTime(data.over_time);
    renderChartByType(data.by_type);
    renderChartByStatus(data.by_status);

    loadAdminHazards();
    loadAdminUsers();
  } catch (err) {
    console.error('Admin dashboard error:', err);
  }
}

function renderChartOverTime(data) {
  const ctx = document.getElementById('chartOverTime');
  if (!ctx) return;
  if (chartOverTime) chartOverTime.destroy();
  const labels = data.map(d => d.date);
  const values = data.map(d => d.count);
  const total = values.reduce((a, b) => a + b, 0);
  ctx.setAttribute('aria-label', `Line chart: ${total} hazard reports over ${labels.length} day${labels.length === 1 ? '' : 's'}.`);
  chartOverTime = new Chart(ctx, {
    type: 'line',
    data: { labels, datasets: [{ label: 'Reports', data: values, borderColor: '#2563eb', backgroundColor: 'rgba(37,99,235,0.1)', fill: true, tension: 0.3 }] },
    options: { responsive: true, plugins: { title: { display: true, text: 'Reports Over Time' } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
  });
}

function renderChartByType(data) {
  const ctx = document.getElementById('chartByType');
  if (!ctx) return;
  if (chartByType) chartByType.destroy();
  const labels = data.map(d => d.type.replace(/_/g, ' '));
  const values = data.map(d => d.count);
  const colors = labels.map(l => getColor(l.toLowerCase().replace(/ /g, '_')));
  ctx.setAttribute('aria-label', 'Doughnut chart: reports by hazard type. ' + labels.map((l, i) => `${l}: ${values[i]}`).join('; ') + '.');
  chartByType = new Chart(ctx, {
    type: 'doughnut',
    data: { labels, datasets: [{ data: values, backgroundColor: colors }] },
    options: { responsive: true, plugins: { title: { display: true, text: 'By Hazard Type' } } }
  });
}

function renderChartByStatus(data) {
  const ctx = document.getElementById('chartByStatus');
  if (!ctx) return;
  if (chartByStatus) chartByStatus.destroy();
  const labels = data.map(d => d.status);
  const values = data.map(d => d.count);
  ctx.setAttribute('aria-label', 'Bar chart: reports by status. ' + labels.map((l, i) => `${l}: ${values[i]}`).join('; ') + '.');
  chartByStatus = new Chart(ctx, {
    type: 'bar',
    data: { labels, datasets: [{ label: 'Count', data: values, backgroundColor: ['#f59e0b','#3b82f6','#10b981','#6b7280'] }] },
    options: { responsive: true, plugins: { title: { display: true, text: 'By Status' } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
  });
}

function switchAdminTab(tabId) {
  document.querySelectorAll('#adminTabs .tab').forEach(t => {
    const active = t.dataset.tab === tabId;
    t.classList.toggle('active', active);
    t.setAttribute('aria-selected', active ? 'true' : 'false');
    t.tabIndex = active ? 0 : -1;
  });

  ['adminStats', 'adminHazards', 'adminUsers'].forEach(id => {
    document.getElementById(id).classList.toggle('hidden', id !== tabId);
  });
}

// Roving-tabkey support for the admin tablist.
document.getElementById('adminTabs').addEventListener('keydown', (e) => {
  if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) return;
  const tabs = [...document.querySelectorAll('#adminTabs .tab')];
  const idx = tabs.indexOf(document.activeElement);
  if (idx === -1) return;
  e.preventDefault();
  let next = idx;
  if (e.key === 'ArrowLeft') next = (idx - 1 + tabs.length) % tabs.length;
  if (e.key === 'ArrowRight') next = (idx + 1) % tabs.length;
  if (e.key === 'Home') next = 0;
  if (e.key === 'End') next = tabs.length - 1;
  tabs[next].focus();
  switchAdminTab(tabs[next].dataset.tab);
});

async function loadAdminHazards() {
  try {
    const data = await api('GET', '/admin/hazards?per_page=50');
    const items = data.data || data;
    const container = document.getElementById('adminHazards');
    if (!items || items.length === 0) {
      container.innerHTML = '<div class="empty-state"><p>No hazards</p></div>';
      return;
    }
    container.innerHTML = items.map(h => {
      const id = toId(h.id);
      const typeLabel = escapeHtml(String(h.type).replace(/_/g, ' '));
      return `
      <div class="hazard-item">
        <div class="hazard-header">
          <span class="hazard-type ${escapeHtml(h.type)}">${typeLabel}</span>
          <select data-action="update-status" data-id="${id}" class="form-input status-select" aria-label="Status for ${typeLabel}">
            <option value="pending" ${h.status === 'pending' ? 'selected' : ''}>Pending</option>
            <option value="confirmed" ${h.status === 'confirmed' ? 'selected' : ''}>Confirmed</option>
            <option value="resolved" ${h.status === 'resolved' ? 'selected' : ''}>Resolved</option>
            <option value="dismissed" ${h.status === 'dismissed' ? 'selected' : ''}>Dismissed</option>
          </select>
        </div>
        <div class="hazard-desc">${escapeHtml(h.description)}</div>
        <div class="hazard-meta">${escapeHtml(h.user?.name || 'Unknown')} · ${escapeHtml(new Date(h.created_at).toLocaleDateString())}</div>
      </div>
    `;
    }).join('');
  } catch (err) {
    document.getElementById('adminHazards').innerHTML = `<div class="alert alert-error">${escapeHtml(err.message)}</div>`;
  }
}

async function updateHazardStatus(id, status) {
  try {
    await api('PUT', `/admin/hazards/${toId(id)}`, { status });
    loadHazards();
    loadAdminDashboard();
  } catch (err) {
    showError(err.message);
  }
}

async function loadAdminUsers() {
  try {
    const data = await api('GET', '/admin/users');
    const items = data.data || data;
    const container = document.getElementById('adminUsers');
    if (!items || items.length === 0) {
      container.innerHTML = '<div class="empty-state"><p>No users</p></div>';
      return;
    }
    container.innerHTML = items.map(u => {
      const id = toId(u.id);
      const name = escapeHtml(u.name);
      return `
      <div class="hazard-item">
        <div class="flex justify-between items-center">
          <div>
            <strong>${name}</strong>
            <div class="hazard-meta">${escapeHtml(u.email)} · Role: ${escapeHtml(u.role)} · ${escapeHtml(u.hazards_count || 0)} reports</div>
          </div>
          <div class="flex gap-2">
            <button type="button" class="btn btn-outline btn-sm" data-action="toggle-role" data-id="${id}" data-role="${escapeHtml(u.role)}">
              ${u.role === 'admin' ? 'Demote' : 'Promote'}
            </button>
            <button type="button" class="btn btn-danger btn-sm" data-action="suspend-user" data-id="${id}">Suspend</button>
          </div>
        </div>
      </div>
    `;
    }).join('');
  } catch (err) {
    document.getElementById('adminUsers').innerHTML = `<div class="alert alert-error">${escapeHtml(err.message)}</div>`;
  }
}

async function toggleUserRole(id, currentRole) {
  const newRole = currentRole === 'admin' ? 'user' : 'admin';
  try {
    await api('PUT', `/admin/users/${toId(id)}`, { role: newRole });
    loadAdminUsers();
  } catch (err) {
    showError(err.message);
  }
}

async function suspendUser(id) {
  try {
    await api('POST', `/admin/users/${toId(id)}/suspend`);
    loadAdminUsers();
  } catch (err) {
    showError(err.message);
  }
}

/* ============================
   Init
   ============================ */
document.addEventListener('DOMContentLoaded', () => {
  updateUI();
});
