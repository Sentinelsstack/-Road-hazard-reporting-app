<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#2563eb" media="(prefers-color-scheme: light)">
  <meta name="theme-color" content="#0f172a" media="(prefers-color-scheme: dark)">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="RoadHaz">
  <base href="{{ rtrim(url('/'), '/') }}/">
  <link rel="manifest" href="manifest.json">
  <link rel="icon" href="icons/icon-192.png" sizes="192x192">
  <link rel="apple-touch-icon" href="icons/icon-192.png">
  <title>Road Hazard Reporter</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha384-sHL9NAb7lN7rfvG5lfHpm643Xkcjzp4jFvuavGOndn6pjVqS6ny56CAt3nsEVT4H" crossorigin="anonymous">
  <link rel="stylesheet" href="css/app.css">
  {{-- Runtime config for public/js/app.js (CSP-safe: no inline scripts). --}}
  <meta name="api-base" content="{{ rtrim(url('/'), '/') }}/">
  <meta name="pusher-key" content="{{ config('broadcasting.connections.pusher.key') }}">
  <meta name="pusher-cluster" content="{{ config('broadcasting.connections.pusher.options.cluster') }}">
</head>
<body>
  <!-- Top Navigation -->
  <nav class="nav" id="nav" aria-label="Top">
    <span class="nav-title">RoadHaz</span>
    <div class="nav-actions">
      <span class="nav-user hidden" id="navUser"></span>
      <button type="button" class="btn-logout hidden" id="btnLogout" data-action="logout">Logout</button>
      <button type="button" class="theme-toggle" id="themeToggle" data-action="toggle-theme" aria-label="Toggle color theme" aria-pressed="false"><span aria-hidden="true">☀️</span></button>
    </div>
  </nav>

  <main id="main">
    <!-- Splash / Loading -->
    <div class="section active" id="splashSection" tabindex="-1">
      <div class="text-center splash-inner">
        <div class="splash-icon" aria-hidden="true">🛣️</div>
        <h2 class="splash-title">Road Hazard Reporter</h2>
        <p class="splash-sub">Report and track road hazards in your area</p>
        <div class="spinner" role="status" aria-label="Loading"></div>
      </div>
    </div>

    <!-- Login -->
    <div class="section" id="loginSection" tabindex="-1">
      <div class="card">
        <h2 class="card-title">Sign In</h2>
        <div id="loginAlert" class="alert" role="alert"></div>
        <form id="loginForm">
          <div class="form-group">
            <label for="loginEmail">Email</label>
            <input type="email" class="form-input" id="loginEmail" name="email" required autocomplete="email" placeholder="you@example.com">
          </div>
          <div class="form-group">
            <label for="loginPassword">Password</label>
            <input type="password" class="form-input" id="loginPassword" name="password" required autocomplete="current-password" minlength="12" placeholder="At least 12 characters">
          </div>
          <button type="submit" class="btn btn-primary" id="loginBtn">Sign In</button>
        </form>
        <div class="form-footer">
          Don't have an account?
          <button type="button" class="auth-toggle" data-action="show-section" data-section="registerSection">Register</button>
        </div>
      </div>
    </div>

    <!-- Register -->
    <div class="section" id="registerSection" tabindex="-1">
      <div class="card">
        <h2 class="card-title">Create Account</h2>
        <div id="registerAlert" class="alert" role="alert"></div>
        <form id="registerForm">
          <div class="form-group">
            <label for="regName">Full Name</label>
            <input type="text" class="form-input" id="regName" name="name" required autocomplete="name" placeholder="Your name">
          </div>
          <div class="form-group">
            <label for="regEmail">Email</label>
            <input type="email" class="form-input" id="regEmail" name="email" required autocomplete="email" placeholder="you@example.com">
          </div>
          <div class="form-group">
            <label for="regPassword">Password</label>
            <input type="password" class="form-input" id="regPassword" name="password" required autocomplete="new-password" minlength="12" placeholder="At least 12 characters">
          </div>
          <button type="submit" class="btn btn-primary" id="regBtn">Create Account</button>
        </form>
        <div class="form-footer">
          Already have an account?
          <button type="button" class="auth-toggle" data-action="show-section" data-section="loginSection">Sign In</button>
        </div>
      </div>
    </div>

    <!-- Map -->
    <div class="section" id="mapSection" tabindex="-1">
      <div id="mapAlert" class="alert" role="alert"></div>
      <div class="map-toolbar">
        <button type="button" class="btn btn-outline btn-sm" data-action="locate-me" id="locateMeBtn">
          <span aria-hidden="true">📍</span> Locate Me
        </button>
        <button type="button" class="btn btn-outline btn-sm" data-action="refresh-map"><span aria-hidden="true">🔄</span> Refresh</button>
      </div>
      <div id="map" aria-label="Map of reported road hazards" role="region"></div>
      <h3 class="sr-only" id="hazardListHeading">Reported hazards list</h3>
      <div id="hazardList" aria-labelledby="hazardListHeading"></div>
    </div>

    <!-- Report Form -->
    <div class="section" id="reportSection" tabindex="-1">
      <div class="card">
        <h2 class="card-title">Report a Hazard</h2>
        <div id="reportAlert" class="alert" role="alert"></div>
        <form id="reportForm">
          <div class="form-group">
            <span class="label-like" id="locationLabel">Location</span>
            <div class="coords-info" id="coordsInfo" aria-live="polite">
              <span id="coordsText">Tap "Get Location" to capture your position</span>
            </div>
            <button type="button" class="btn btn-outline btn-sm" data-action="get-location" id="locBtn" aria-describedby="coordsInfo">
              <span aria-hidden="true">📍</span> Get Location
            </button>
            <div class="coords-inputs">
              <div class="coords-field">
                <label for="reportLat" class="sr-only">Latitude</label>
                <input type="number" step="any" class="form-input" id="reportLat" placeholder="Latitude" aria-label="Latitude">
              </div>
              <div class="coords-field">
                <label for="reportLng" class="sr-only">Longitude</label>
                <input type="number" step="any" class="form-input" id="reportLng" placeholder="Longitude" aria-label="Longitude">
              </div>
            </div>
          </div>
          <div class="form-group">
            <label for="reportType">Hazard Type</label>
            <select class="form-input" id="reportType" required>
              <option value="">Select type...</option>
              <option value="pothole">Pothole</option>
              <option value="fallen_tree">Fallen Tree</option>
              <option value="flooding">Flooding</option>
              <option value="broken_traffic_light">Broken Traffic Light</option>
              <option value="road_damage">Road Damage</option>
              <option value="landslide">Landslide</option>
              <option value="manhole_cover_missing">Missing Manhole Cover</option>
              <option value="road_accident">Road Accident</option>
            </select>
          </div>
          <div class="form-group">
            <label for="reportDesc">Description</label>
            <textarea class="form-input" id="reportDesc" required maxlength="2000" placeholder="Describe the hazard..."></textarea>
          </div>
          <div class="form-group">
            <label for="reportImage">Photo (optional)</label>
            <input type="file" class="form-input" id="reportImage" accept="image/jpeg,image/png,image/gif,image/webp" capture="environment">
          </div>
          <button type="submit" class="btn btn-primary" id="reportBtn">Submit Report</button>
        </form>
      </div>
    </div>

    <!-- Hazard Detail / Chat -->
    <div class="section" id="detailSection" tabindex="-1">
      <div class="card">
        <button type="button" class="btn btn-outline btn-sm mb-2" data-action="go-back"><span aria-hidden="true">←</span> Back</button>
        <div id="detailContent"></div>
      </div>
      <div class="card">
        <h2 class="card-title">Chat with Admin</h2>
        <div class="chat-messages" id="chatMessages" aria-live="polite" aria-relevant="additions"></div>
        <div class="chat-input-row">
          <label for="chatInput" class="sr-only">Message</label>
          <input type="text" class="form-input" id="chatInput" placeholder="Type a message..." maxlength="2000" autocomplete="off">
          <button type="button" class="btn btn-primary btn-sm" data-action="send-chat" id="chatBtn">Send</button>
        </div>
      </div>
    </div>

    <!-- Admin Dashboard -->
    <div class="section" id="adminSection" tabindex="-1">
      <div class="stats-grid" id="statsGrid"></div>

      <div class="tabs" id="adminTabs" role="tablist" aria-label="Admin dashboard views">
        <button type="button" class="tab active" role="tab" id="tab-adminStats" data-tab="adminStats" data-action="switch-tab" aria-selected="true" aria-controls="adminStats">Stats</button>
        <button type="button" class="tab" role="tab" id="tab-adminHazards" data-tab="adminHazards" data-action="switch-tab" aria-selected="false" aria-controls="adminHazards" tabindex="-1">Hazards</button>
        <button type="button" class="tab" role="tab" id="tab-adminUsers" data-tab="adminUsers" data-action="switch-tab" aria-selected="false" aria-controls="adminUsers" tabindex="-1">Users</button>
      </div>

      <div id="adminStats" role="tabpanel" aria-labelledby="tab-adminStats">
        <div class="chart-container">
          <canvas id="chartOverTime" role="img" aria-label="Reports over time chart"></canvas>
        </div>
        <div class="chart-container">
          <canvas id="chartByType" role="img" aria-label="Reports by hazard type chart"></canvas>
        </div>
        <div class="chart-container">
          <canvas id="chartByStatus" role="img" aria-label="Reports by status chart"></canvas>
        </div>
      </div>

      <div id="adminHazards" role="tabpanel" aria-labelledby="tab-adminHazards" class="hidden"></div>
      <div id="adminUsers" role="tabpanel" aria-labelledby="tab-adminUsers" class="hidden"></div>
    </div>
  </main>

  <!-- Bottom Navigation -->
  <nav class="bottom-nav" id="bottomNav" aria-label="Primary">
    <button type="button" class="nav-item" data-section="mapSection" data-action="show-section" aria-current="false">
      <span class="icon" aria-hidden="true">🗺️</span> Map
    </button>
    <button type="button" class="nav-item" data-section="reportSection" data-action="show-section" aria-current="false">
      <span class="icon" aria-hidden="true">➕</span> Report
    </button>
    <button type="button" class="nav-item" data-section="adminSection" data-action="show-section" aria-current="false">
      <span class="icon" aria-hidden="true">📊</span> Admin
    </button>
  </nav>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha384-cxOPjt7s7Iz04uaHJceBmS+qpjv2JkIHNVcuOrM+YHwZOmJGBXI00mdUXEq65HTH" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" integrity="sha384-9nhczxUqK87bcKHh20fSQcTGD4qq5GhayNYSYWqwBkINBhOfQLg/P5HG5lF1urn4" crossorigin="anonymous"></script>
  <script src="https://js.pusher.com/8.2/pusher.min.js" integrity="sha384-gA0TPBlnosOv77mNKhqDqUd7BMOqU7f5VlaEGFdyCus4A5l7JHELZ4K5dQMBSL1j" crossorigin="anonymous"></script>
  <script src="js/app.js"></script>
  <script src="js/sw-register.js"></script>
</body>
</html>
