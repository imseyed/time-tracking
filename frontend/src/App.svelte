<script>
  import { onMount } from 'svelte'

  const API_BASE = 'http://localhost:8080/api.php'

  let currentUser = null
  let view = 'entry'
  let message = ''
  let error = ''

  let loginForm = { username: 'admin', password: 'public' }
  let users = []
  let projects = []
  let reportDetails = []
  let recentEntries = []

  const todayJ = toJalali(new Date().toISOString().slice(0, 10))
  const thirtyDaysAgo = new Date(Date.now() - 29 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10)

  let form = { user_id: '', project_id: '', work_date_j: todayJ, start_time: '', end_time: '', description: '' }
  let reportFilter = { user_id: '', project_id: '', from_date_j: toJalali(thirtyDaysAgo), to_date_j: todayJ }

  let userForm = { full_name: '', username: '', password: '', role: 'user' }
  let selectedUser = null
  let projectForm = { id: '', name: '', color: '#FC572C' }

  const menuByRole = {
    admin: [
      { key: 'entry', label: '⏱️ ثبت ساعت' },
      { key: 'report', label: '📊 گزارش‌گیری' },
      { key: 'users', label: '👥 مدیریت کاربران' },
      { key: 'projects', label: '🗂️ مدیریت پروژه‌ها' }
    ],
    user: [
      { key: 'entry', label: '⏱️ ثبت ساعت' },
      { key: 'report', label: '📊 گزارش من' }
    ]
  }

  $: menu = currentUser ? menuByRole[currentUser.role] ?? [] : []
  $: dailyChart = buildDailyChart(reportDetails, reportFilter.from_date_j, reportFilter.to_date_j)
  $: maxDaily = Math.max(1, ...dailyChart.map((d) => d.hours))

  function clearAlerts() {
    message = ''
    error = ''
  }

  function authParams() {
    return currentUser ? `&auth_user_id=${currentUser.id}` : ''
  }

  async function request(action, method = 'GET', payload = null) {
    const url = `${API_BASE}?action=${action}${method === 'GET' ? authParams() : ''}`
    const options = { method, headers: { 'Content-Type': 'application/json' } }
    if (method !== 'GET') options.body = JSON.stringify({ ...(payload || {}), auth_user_id: currentUser?.id })

    const res = await fetch(url, options)
    const json = await res.json()
    if (!res.ok) throw new Error(json.error || 'خطا در ارتباط با سرور')
    return json
  }

  async function doLogin() {
    clearAlerts()
    try {
      const json = await request('login', 'POST', loginForm)
      currentUser = json.user
      localStorage.setItem('tt_user', JSON.stringify(currentUser))
      form.user_id = String(currentUser.id)
      await loadAppData()
      message = `✅ خوش آمدید ${currentUser.full_name}`
    } catch (e) {
      error = e.message
    }
  }

  function logout() {
    currentUser = null
    localStorage.removeItem('tt_user')
    view = 'entry'
    clearAlerts()
  }

  async function loadUsers() {
    if (currentUser?.role !== 'admin') return
    const json = await request('users')
    users = json.data || []
  }

  async function loadProjects() {
    const json = await request('projects')
    projects = json.data || []
    if (!form.project_id && projects.length) form.project_id = String(projects[0].id)
  }

  async function loadRecentEntries() {
    const json = await request('entry-recent')
    recentEntries = json.data || []
  }

  async function loadReport(showMessage = false) {
    if (!showMessage) clearAlerts()
    try {
      const qs = new URLSearchParams({ action: 'report', auth_user_id: String(currentUser.id) })
      if (reportFilter.user_id) qs.set('user_id', reportFilter.user_id)
      if (reportFilter.project_id) qs.set('project_id', reportFilter.project_id)
      if (reportFilter.from_date_j) qs.set('from_date', toGregorian(reportFilter.from_date_j))
      if (reportFilter.to_date_j) qs.set('to_date', toGregorian(reportFilter.to_date_j))

      const res = await fetch(`${API_BASE}?${qs.toString()}`)
      const json = await res.json()
      if (!res.ok) throw new Error(json.error || 'خطا در گزارش')
      reportDetails = json.details || []
    } catch (e) {
      error = e.message
    }
  }

  async function submitEntry() {
    clearAlerts()
    try {
      await request('entry-create', 'POST', {
        ...form,
        work_date: toGregorian(form.work_date_j),
        user_id: Number(form.user_id),
        project_id: Number(form.project_id)
      })
      message = '✅ ثبت ساعت با موفقیت انجام شد.'
      form.start_time = ''
      form.end_time = ''
      form.description = ''
      await loadRecentEntries()
      await loadReport(true)
    } catch (e) {
      error = e.message
    }
  }

  async function createUser() {
    clearAlerts()
    try {
      await request('user-create', 'POST', userForm)
      message = '✅ کاربر ایجاد شد.'
      userForm = { full_name: '', username: '', password: '', role: 'user' }
      await loadUsers()
    } catch (e) { error = e.message }
  }

  async function editUser() {
    if (!selectedUser) return
    clearAlerts()
    try {
      await request('user-update', 'POST', selectedUser)
      message = '✅ کاربر ویرایش شد.'
      await loadUsers()
    } catch (e) { error = e.message }
  }

  async function deleteUser(id) {
    clearAlerts()
    try {
      await request('user-delete', 'POST', { id })
      message = '✅ کاربر حذف شد.'
      if (selectedUser?.id === id) selectedUser = null
      await loadUsers()
    } catch (e) { error = e.message }
  }

  async function saveProject() {
    clearAlerts()
    try {
      if (projectForm.id) {
        await request('project-update', 'POST', projectForm)
        message = '✅ پروژه ویرایش شد.'
      } else {
        await request('project-create', 'POST', projectForm)
        message = '✅ پروژه ایجاد شد.'
      }
      projectForm = { id: '', name: '', color: '#FC572C' }
      await loadProjects()
    } catch (e) { error = e.message }
  }

  async function deleteProject(id) {
    clearAlerts()
    try {
      await request('project-delete', 'POST', { id })
      message = '✅ پروژه حذف شد.'
      await loadProjects()
    } catch (e) { error = e.message }
  }

  function startEditProject(p) {
    projectForm = { id: p.id, name: p.name, color: p.color }
  }

  function startEditUser(u) {
    selectedUser = { id: u.id, full_name: u.full_name, role: u.role, password: '' }
  }

  async function loadAppData() {
    await loadProjects()
    await loadRecentEntries()
    await loadReport()
    if (currentUser?.role === 'admin') await loadUsers()
  }

  onMount(async () => {
    const raw = localStorage.getItem('tt_user')
    if (!raw) return
    currentUser = JSON.parse(raw)
    form.user_id = String(currentUser.id)
    await loadAppData()
  })

  function toJalali(gDate) {
    if (!gDate) return ''
    const [gy, gm, gd] = gDate.split('-').map(Number)
    const [jy, jm, jd] = d2j(g2d(gy, gm, gd))
    return `${jy}/${String(jm).padStart(2, '0')}/${String(jd).padStart(2, '0')}`
  }

  function toGregorian(jDate) {
    if (!jDate) return ''
    const [jy, jm, jd] = jDate.split('/').map(Number)
    const [gy, gm, gd] = d2g(j2d(jy, jm, jd))
    return `${gy}-${String(gm).padStart(2, '0')}-${String(gd).padStart(2, '0')}`
  }

  function buildDailyChart(rows, fromJ, toJ) {
    const out = []
    if (!fromJ || !toJ) return out
    const fromG = toGregorian(fromJ)
    const toG = toGregorian(toJ)
    let cur = new Date(`${fromG}T00:00:00Z`)
    const end = new Date(`${toG}T00:00:00Z`)

    const sumByDay = {}
    for (const r of rows) sumByDay[r.work_date] = (sumByDay[r.work_date] || 0) + Number(r.duration_minutes || 0)

    while (cur <= end) {
      const y = cur.getUTCFullYear()
      const m = String(cur.getUTCMonth() + 1).padStart(2, '0')
      const d = String(cur.getUTCDate()).padStart(2, '0')
      const key = `${y}-${m}-${d}`
      out.push({
        day: toJalali(key).slice(5),
        hours: Number(((sumByDay[key] || 0) / 60).toFixed(2))
      })
      cur = new Date(cur.getTime() + 86400000)
    }
    return out
  }

  function div(a, b) { return ~~(a / b) }
  function g2d(gy, gm, gd) { let d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4) + div(153 * ((gm + 9) % 12) + 2, 5) + gd - 34840408; d = d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752; return d }
  function d2g(jdn) { let j = 4 * jdn + 139361631; j = j + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908; const i = div((j % 1461), 4) * 5 + 308; const gd = div(i % 153, 5) + 1; const gm = (div(i, 153) % 12) + 1; const gy = div(j, 1461) - 100100 + div(8 - gm, 6); return [gy, gm, gd] }
  function j2d(jy, jm, jd) { jy += 1595; return -355668 + 365 * jy + div(jy, 33) * 8 + div((jy % 33 + 3), 4) + jd + ((jm < 7) ? (jm - 1) * 31 : (jm - 7) * 30 + 186) }
  function d2j(jdn) { let jy = -1595 + 33 * div(jdn, 12053); jdn %= 12053; jy += 4 * div(jdn, 1461); jdn %= 1461; if (jdn > 365) { jy += div(jdn - 1, 365); jdn = (jdn - 1) % 365; } const jm = (jdn < 186) ? 1 + div(jdn, 31) : 7 + div(jdn - 186, 30); const jd = 1 + ((jdn < 186) ? (jdn % 31) : ((jdn - 186) % 30)); return [jy, jm, jd] }
</script>

{#if !currentUser}
  <main class="auth-page">
    <section class="card">
      <h1>🔐 ورود</h1>
      <p>اکانت پیش‌فرض مدیر: <strong>admin / public</strong></p>
      {#if error}<div class="alert error">{error}</div>{/if}
      <label>نام کاربری <input bind:value={loginForm.username} /></label>
      <label>رمز عبور <input type="password" bind:value={loginForm.password} /></label>
      <button class="primary" on:click={doLogin}>🚪 ورود</button>
    </section>
  </main>
{:else}
  <main class="panel">
    <aside class="sidebar">
      <h2>⏰ زمان‌سنجی</h2>
      <small>{currentUser.full_name}</small>
      <nav>{#each menu as m}<button class:active={view === m.key} on:click={() => (view = m.key)}>{m.label}</button>{/each}</nav>
      <button class="logout" on:click={logout}>🚪 خروج</button>
    </aside>

    <section class="content">
      {#if message}<div class="alert success">{message}</div>{/if}
      {#if error}<div class="alert error">{error}</div>{/if}

      {#if view === 'entry'}
        <div class="card">
          <h3>⏱️ ثبت ساعت</h3>
          <div class="grid">
            {#if currentUser.role === 'admin'}
              <label>کاربر
                <select bind:value={form.user_id}>{#each users as u}<option value={u.id}>{u.full_name}</option>{/each}</select>
              </label>
            {/if}
            <label>پروژه
              <select bind:value={form.project_id}>{#each projects as p}<option value={p.id}>{p.name}</option>{/each}</select>
            </label>
            <label>تاریخ شمسی <input placeholder="1405/01/31" bind:value={form.work_date_j} /></label>
            <label>شروع <input type="time" bind:value={form.start_time} /></label>
            <label>پایان <input type="time" bind:value={form.end_time} /></label>
          </div>
          <label>شرح <textarea rows="3" bind:value={form.description}></textarea></label>
          <button class="primary" on:click={submitEntry}>💾 ثبت</button>

          <h4>🕘 25 رکورد اخیر شما</h4>
          <div class="table-wrap">
            <table>
              <thead><tr><th>تاریخ</th><th>پروژه</th><th>شروع</th><th>پایان</th><th>دقیقه</th><th>شرح</th></tr></thead>
              <tbody>
                {#if recentEntries.length === 0}
                  <tr><td colspan="6">رکوردی ثبت نشده است.</td></tr>
                {:else}
                  {#each recentEntries as r}
                    <tr><td>{toJalali(r.work_date)}</td><td>{r.project_name}</td><td>{r.start_time}</td><td>{r.end_time}</td><td>{r.duration_minutes}</td><td>{r.description}</td></tr>
                  {/each}
                {/if}
              </tbody>
            </table>
          </div>
        </div>
      {/if}

      {#if view === 'users' && currentUser.role === 'admin'}
        <div class="card">
          <h3>👥 لیست کاربران</h3>
          <button class="primary" on:click={() => (selectedUser = null)}>➕ ایجاد کاربر جدید</button>
          <div class="table-wrap">
            <table>
              <thead><tr><th>نام</th><th>نام کاربری</th><th>نقش</th><th>عملیات</th></tr></thead>
              <tbody>
                {#each users as u}
                  <tr>
                    <td>{u.full_name}</td><td>{u.username}</td><td>{u.role}</td>
                    <td>
                      <button on:click={() => startEditUser(u)}>✏️ ویرایش</button>
                      <button on:click={() => deleteUser(u.id)}>🗑️ حذف</button>
                    </td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>

          <h4>{selectedUser ? 'ویرایش کاربر' : 'ایجاد کاربر'}</h4>
          {#if selectedUser}
            <label>نام <input bind:value={selectedUser.full_name} /></label>
            <label>نقش <select bind:value={selectedUser.role}><option value="user">کاربر</option><option value="admin">ادمین</option></select></label>
            <label>رمز جدید (اختیاری) <input type="password" bind:value={selectedUser.password} /></label>
            <button class="primary" on:click={editUser}>✏️ ذخیره ویرایش</button>
          {:else}
            <label>نام <input bind:value={userForm.full_name} /></label>
            <label>نام کاربری <input bind:value={userForm.username} /></label>
            <label>رمز <input type="password" bind:value={userForm.password} /></label>
            <label>نقش <select bind:value={userForm.role}><option value="user">کاربر</option><option value="admin">ادمین</option></select></label>
            <button class="primary" on:click={createUser}>➕ ایجاد</button>
          {/if}
        </div>
      {/if}

      {#if view === 'projects' && currentUser.role === 'admin'}
        <div class="card">
          <h3>🗂️ مدیریت پروژه‌ها</h3>
          <label>نام پروژه <input bind:value={projectForm.name} /></label>
          <label>رنگ <input type="color" bind:value={projectForm.color} /></label>
          <button class="primary" on:click={saveProject}>{projectForm.id ? '✏️ ذخیره ویرایش' : '➕ ایجاد پروژه'}</button>

          <div class="table-wrap">
            <table>
              <thead><tr><th>نام</th><th>رنگ</th><th>عملیات</th></tr></thead>
              <tbody>
                {#each projects as p}
                  <tr>
                    <td>{p.name}</td><td><span class="color-dot" style={`background:${p.color}`}></span>{p.color}</td>
                    <td>
                      <button on:click={() => startEditProject(p)}>✏️ ویرایش</button>
                      <button on:click={() => deleteProject(p.id)}>🗑️ حذف</button>
                    </td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>
        </div>
      {/if}

      {#if view === 'report'}
        <div class="card">
          <h3>📊 گزارش‌گیری</h3>
          <div class="grid">
            {#if currentUser.role === 'admin'}
              <label>کاربر <select bind:value={reportFilter.user_id}><option value="">همه</option>{#each users as u}<option value={u.id}>{u.full_name}</option>{/each}</select></label>
            {/if}
            <label>پروژه <select bind:value={reportFilter.project_id}><option value="">همه</option>{#each projects as p}<option value={p.id}>{p.name}</option>{/each}</select></label>
            <label>از تاریخ (شمسی) <input bind:value={reportFilter.from_date_j} placeholder="1405/01/01" /></label>
            <label>تا تاریخ (شمسی) <input bind:value={reportFilter.to_date_j} placeholder="1405/01/30" /></label>
          </div>
          <button class="primary" on:click={loadReport}>🔍 اعمال فیلتر</button>

          <h4>نمودار عمودی ساعات روزانه</h4>
          <div class="vchart">
            {#each dailyChart as day}
              <div class="bar-col">
                <div class="bar" style={`height:${(day.hours / maxDaily) * 180}px`} title={`${day.hours}h`}></div>
                <small>{day.day}</small>
              </div>
            {/each}
          </div>

          <div class="table-wrap">
            <table>
              <thead><tr><th>تاریخ</th><th>کاربر</th><th>پروژه</th><th>شروع</th><th>پایان</th><th>دقیقه</th><th>شرح</th></tr></thead>
              <tbody>
                {#if reportDetails.length === 0}
                  <tr><td colspan="7">رکوردی یافت نشد.</td></tr>
                {:else}
                  {#each reportDetails as r}
                    <tr><td>{toJalali(r.work_date)}</td><td>{r.user_name}</td><td>{r.project_name}</td><td>{r.start_time}</td><td>{r.end_time}</td><td>{r.duration_minutes}</td><td>{r.description}</td></tr>
                  {/each}
                {/if}
              </tbody>
            </table>
          </div>
        </div>
      {/if}
    </section>
  </main>
{/if}

<style>
  @font-face { font-family: 'IRANSansX'; src: url('/fonts/IRANSansXFaNum-Medium.woff2') format('woff2'); font-weight: 500; }
  @font-face { font-family: 'IRANSansX'; src: url('/fonts/IRANSansXFaNum-ExtraBold.woff') format('woff'); font-weight: 800; }
  :global(body) { margin: 0; font-family: 'IRANSansX', Tahoma, sans-serif; background: #fff7f3; direction: rtl; }
  .panel { min-height: 100vh; display: grid; grid-template-columns: 280px 1fr; }
  .sidebar { background: linear-gradient(180deg,#fc572c,#e84a24); color: #fff; padding: 16px; display:flex; flex-direction:column; gap:12px; }
  nav { display:grid; gap:8px; }
  button { font: inherit; border: 1px solid #ffccbd; background:#fff; border-radius:10px; padding:9px 10px; cursor:pointer; }
  nav button,.logout { background: rgba(255,255,255,0.18); border-color: rgba(255,255,255,.35); color:white; }
  nav button.active { background: #fff; color:#fc572c; font-weight: 800; }
  .logout { margin-top:auto; }
  .content { padding: 22px; }
  .card { background:#fff; border:1px solid #ffd7cb; border-radius:14px; padding:16px; box-shadow:0 8px 24px rgba(252,87,44,.1); }
  .primary { background:#fc572c; color:white; border-color:#fc572c; }
  .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:10px; }
  label { display:flex; flex-direction:column; gap:6px; margin-bottom:10px; }
  input,select,textarea { font: inherit; border:1px solid #ffc7b6; border-radius:10px; padding:9px; }
  .alert { margin-bottom:10px; padding:10px; border-radius:10px; }
  .success { background:#ffe7de; color:#8f2a10; }
  .error { background:#ffe3e3; color:#a02727; }
  .table-wrap { overflow-x:auto; margin-top:10px; }
  table { width:100%; border-collapse:collapse; }
  th,td { border-bottom:1px solid #ffe2d8; text-align:right; padding:8px; }
  .vchart { display:flex; align-items:flex-end; gap:8px; min-height:220px; padding:8px; overflow-x:auto; border:1px dashed #ffd3c6; border-radius:10px; margin:10px 0; }
  .bar-col { min-width:28px; display:flex; flex-direction:column; align-items:center; gap:6px; }
  .bar { width:22px; background:#fc572c; border-radius:7px 7px 0 0; }
  .color-dot { width:12px; height:12px; border-radius:50%; display:inline-block; margin-left:6px; }
  .auth-page { min-height:100vh; display:grid; place-items:center; padding:20px; }
</style>
