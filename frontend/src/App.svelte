<script>
  import { onMount } from 'svelte'

  const API_BASE = 'http://localhost:8080/api.php'

  let currentUser = null
  let view = 'entry'

  let loginForm = { username: 'admin', password: 'public' }
  let users = []
  let projects = []
  let reportDetails = []
  let dailyChart = []
  let recentEntries = []
  let summary = { entries_count: 0, total_hours: 0 }

  let form = {
    user_id: '',
    project_id: '',
    work_date_jalali: '',
    start_time: '',
    end_time: '',
    description: ''
  }

  let reportFilter = { user_id: '', project_id: '', from_date_jalali: '', to_date_jalali: '' }
  let userForm = { id: '', full_name: '', username: '', password: '', role: 'user' }
  let projectForm = { id: '', name: '', color: '#FC572C' }

  let message = ''
  let error = ''
  let userAction = 'none'
  let projectAction = 'list'

  const menuByRole = {
    admin: [
      { key: 'entry', label: 'ثبت ساعت', icon: '⏱️' },
      { key: 'report', label: 'گزارش‌گیری', icon: '📊' },
      { key: 'users', label: 'کاربران', icon: '👥' },
      { key: 'projects', label: 'پروژه‌ها', icon: '📁' }
    ],
    user: [
      { key: 'entry', label: 'ثبت ساعت', icon: '⏱️' },
      { key: 'report', label: 'گزارش من', icon: '📊' }
    ]
  }

  // Jalali conversion
  function div(a, b) { return Math.floor(a / b) }
  function g2d(gy, gm, gd) {
    let d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4) + div(153 * ((gm + 9) % 12) + 2, 5) + gd - 34840408
    d = d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752
    return d
  }
  function d2g(jdn) {
    let j = 4 * jdn + 139361631
    j = j + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908
    const i = div((j % 1461), 4) * 5 + 308
    const gd = div((i % 153), 5) + 1
    const gm = (div(i, 153) % 12) + 1
    const gy = div(j, 1461) - 100100 + div(8 - gm, 6)
    return { gy, gm, gd }
  }
  function jalCal(jy) {
    const breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178]
    const bl = breaks.length
    const gy = jy + 621
    let leapJ = -14
    let jp = breaks[0]
    let jm, jump, leap, n, i

    if (jy < jp || jy >= breaks[bl - 1]) throw new Error('Invalid Jalali year')

    for (i = 1; i < bl; i += 1) {
      jm = breaks[i]
      jump = jm - jp
      if (jy < jm) break
      leapJ = leapJ + div(jump, 33) * 8 + div((jump % 33), 4)
      jp = jm
    }
    n = jy - jp
    leapJ = leapJ + div(n, 33) * 8 + div(((n % 33) + 3), 4)
    if ((jump % 33) === 4 && jump - n === 4) leapJ += 1
    const leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150
    const march = 20 + leapJ - leapG
    if (jump - n < 6) n = n - jump + div(jump + 4, 33) * 33
    leap = ((((n + 1) % 33) - 1) % 4)
    if (leap === -1) leap = 4
    return { leap, gy, march }
  }
  function j2d(jy, jm, jd) {
    const r = jalCal(jy)
    return g2d(r.gy, 3, r.march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1
  }
  function d2j(jdn) {
    const g = d2g(jdn)
    let jy = g.gy - 621
    const r = jalCal(jy)
    const jdn1f = g2d(g.gy, 3, r.march)
    let jd, jm, k
    k = jdn - jdn1f
    if (k >= 0) {
      if (k <= 185) {
        jm = 1 + div(k, 31)
        jd = (k % 31) + 1
        return { jy, jm, jd }
      }
      k -= 186
    } else {
      jy -= 1
      k += 179
      if (r.leap === 1) k += 1
    }
    jm = 7 + div(k, 30)
    jd = (k % 30) + 1
    return { jy, jm, jd }
  }

  const pad = (n) => String(n).padStart(2, '0')
  function gregorianToJalali(dateStr) {
    if (!dateStr) return ''
    const [gy, gm, gd] = dateStr.split('-').map(Number)
    const j = d2j(g2d(gy, gm, gd))
    return `${j.jy}/${pad(j.jm)}/${pad(j.jd)}`
  }
  function jalaliToGregorian(dateStr) {
    if (!dateStr) return ''
    const parts = dateStr.split('/').map(Number)
    if (parts.length !== 3 || parts.some(Number.isNaN)) return ''
    const g = d2g(j2d(parts[0], parts[1], parts[2]))
    return `${g.gy}-${pad(g.gm)}-${pad(g.gd)}`
  }

  function setDefaultReportRange() {
    const today = new Date()
    const past = new Date()
    past.setDate(today.getDate() - 29)
    const t = `${today.getFullYear()}-${pad(today.getMonth() + 1)}-${pad(today.getDate())}`
    const p = `${past.getFullYear()}-${pad(past.getMonth() + 1)}-${pad(past.getDate())}`
    reportFilter.from_date_jalali = gregorianToJalali(p)
    reportFilter.to_date_jalali = gregorianToJalali(t)
    form.work_date_jalali = gregorianToJalali(t)
  }

  const toHours = (m) => (Number(m) / 60).toFixed(2)
  $: maxDaily = Math.max(1, ...dailyChart.map((d) => Number(d.total_minutes || 0)))
  $: userMenu = currentUser ? menuByRole[currentUser.role] ?? [] : []

  function clearAlerts() { error = '' }

  function authParams() {
    return currentUser ? `&auth_user_id=${currentUser.id}` : ''
  }

  async function request(action, method = 'GET', payload = null) {
    const url = `${API_BASE}?action=${action}${method === 'GET' ? authParams() : ''}`
    const options = { method, headers: { 'Content-Type': 'application/json' } }
    if (method !== 'GET') options.body = JSON.stringify({ ...(payload || {}), auth_user_id: currentUser?.id })
    const res = await fetch(url, options)
    const json = await res.json()
    if (!res.ok) throw new Error(json.error || 'خطا')
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
      message = `خوش آمدید ${currentUser.full_name}`
    } catch (e) {
      error = e.message
    }
  }

  function logout() {
    currentUser = null
    localStorage.removeItem('tt_user')
    message = ''
    error = ''
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
    const json = await request('entries-recent')
    recentEntries = (json.data || []).map((r) => ({ ...r, work_date_j: gregorianToJalali(r.work_date) }))
  }

  async function loadReport() {
    clearAlerts()
    try {
      const qs = new URLSearchParams({ action: 'report', auth_user_id: String(currentUser.id) })
      if (reportFilter.user_id) qs.set('user_id', reportFilter.user_id)
      if (reportFilter.project_id) qs.set('project_id', reportFilter.project_id)
      const fromG = jalaliToGregorian(reportFilter.from_date_jalali)
      const toG = jalaliToGregorian(reportFilter.to_date_jalali)
      if (fromG) qs.set('from_date', fromG)
      if (toG) qs.set('to_date', toG)

      const res = await fetch(`${API_BASE}?${qs.toString()}`)
      const json = await res.json()
      if (!res.ok) throw new Error(json.error || 'گزارش دریافت نشد.')

      reportDetails = (json.details || []).map((r) => ({ ...r, work_date_j: gregorianToJalali(r.work_date) }))
      dailyChart = (json.daily_chart || []).map((d) => ({ ...d, work_date_j: gregorianToJalali(d.work_date) }))
      summary = json.summary || summary
    } catch (e) {
      error = e.message
    }
  }

  async function submitEntry() {
    clearAlerts()
    try {
      const workDate = jalaliToGregorian(form.work_date_jalali)
      if (!workDate) throw new Error('تاریخ شمسی معتبر نیست. مثال: 1405/01/31')
      const payload = {
        ...form,
        work_date: workDate,
        user_id: Number(form.user_id),
        project_id: Number(form.project_id)
      }
      await request('entry-create', 'POST', payload)
      message = '✅ ساعت کاری با موفقیت ثبت شد.'
      form.start_time = ''
      form.end_time = ''
      form.description = ''
      await loadRecentEntries()
    } catch (e) {
      error = e.message
    }
  }

  function pickUser(u) {
    userForm = { id: String(u.id), full_name: u.full_name, username: u.username, password: '', role: u.role }
    userAction = 'edit'
  }

  async function saveUser() {
    clearAlerts()
    try {
      if (userAction === 'create') {
        await request('user-create', 'POST', userForm)
        message = '✅ کاربر ایجاد شد.'
      } else if (userAction === 'edit') {
        await request('user-update', 'POST', userForm)
        message = '✅ کاربر ویرایش شد.'
      }
      userAction = 'none'
      userForm = { id: '', full_name: '', username: '', password: '', role: 'user' }
      await loadUsers()
    } catch (e) { error = e.message }
  }

  async function deleteUser(id) {
    clearAlerts()
    try {
      await request('user-delete', 'POST', { id })
      message = '✅ کاربر حذف شد.'
      await loadUsers()
    } catch (e) { error = e.message }
  }

  function pickProject(p) {
    projectForm = { id: String(p.id), name: p.name, color: p.color }
    projectAction = 'edit'
  }

  async function saveProject() {
    clearAlerts()
    try {
      if (projectAction === 'create') {
        await request('project-create', 'POST', projectForm)
        message = '✅ پروژه ایجاد شد.'
      } else if (projectAction === 'edit') {
        await request('project-update', 'POST', projectForm)
        message = '✅ پروژه ویرایش شد.'
      }
      projectAction = 'list'
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

  async function loadAppData() {
    await loadProjects()
    if (currentUser.role === 'admin') await loadUsers()
    await loadRecentEntries()
    await loadReport()
  }

  onMount(async () => {
    setDefaultReportRange()
    const raw = localStorage.getItem('tt_user')
    if (raw) {
      currentUser = JSON.parse(raw)
      form.user_id = String(currentUser.id)
      await loadAppData()
    }
  })
</script>

{#if !currentUser}
  <main class="auth-page">
    <section class="card auth-card">
      <h1>⏱️ ورود به سامانه</h1>
      <p>اکانت پیش‌فرض: <strong>admin / public</strong></p>
      {#if error}<div class="alert error">{error}</div>{/if}
      <label>👤 نام کاربری <input bind:value={loginForm.username} /></label>
      <label>🔐 رمز عبور <input type="password" bind:value={loginForm.password} /></label>
      <button class="primary" on:click={doLogin}>🔓 ورود</button>
    </section>
  </main>
{:else}
  <main class="panel">
    <aside class="sidebar">
      <h2>🟧 Time Panel</h2>
      <small>{currentUser.full_name}</small>
      <nav>
        {#each userMenu as item}
          <button class:active={view === item.key} on:click={() => (view = item.key)}>{item.icon} {item.label}</button>
        {/each}
      </nav>
      <button class="logout" on:click={logout}>🚪 خروج</button>
    </aside>

    <section class="content">
      {#if message}<div class="alert success">{message}</div>{/if}
      {#if error}<div class="alert error">{error}</div>{/if}

      {#if view === 'entry'}
        <div class="card">
          <h3>⏱️ ثبت ساعت کاری</h3>
          <div class="grid">
            {#if currentUser.role === 'admin'}
              <label>👤 کاربر
                <select bind:value={form.user_id}>
                  {#each users as u}<option value={u.id}>{u.full_name}</option>{/each}
                </select>
              </label>
            {/if}
            <label>📁 پروژه
              <select bind:value={form.project_id}>
                {#each projects as p}<option value={p.id}>{p.name}</option>{/each}
              </select>
            </label>
            <label>📅 تاریخ شمسی (yyyy/mm/dd)
              <input bind:value={form.work_date_jalali} placeholder="1405/01/31" />
            </label>
            <label>🕒 شروع <input type="time" bind:value={form.start_time} /></label>
            <label>🕕 پایان <input type="time" bind:value={form.end_time} /></label>
          </div>
          <label>📝 شرح کار <textarea rows="3" bind:value={form.description} /></label>
          <button class="primary" on:click={submitEntry}>💾 ثبت ساعت</button>

          <h4>25 رکورد اخیر شما</h4>
          <div class="table-wrap">
            <table>
              <thead><tr><th>تاریخ</th><th>پروژه</th><th>شروع</th><th>پایان</th><th>دقیقه</th><th>شرح</th></tr></thead>
              <tbody>
                {#if recentEntries.length === 0}
                  <tr><td colspan="6">رکوردی ثبت نشده است.</td></tr>
                {:else}
                  {#each recentEntries as row}
                    <tr>
                      <td>{row.work_date_j}</td><td>{row.project_name}</td><td>{row.start_time}</td><td>{row.end_time}</td><td>{row.duration_minutes}</td><td>{row.description}</td>
                    </tr>
                  {/each}
                {/if}
              </tbody>
            </table>
          </div>
        </div>
      {/if}

      {#if view === 'users' && currentUser.role === 'admin'}
        <div class="card">
          <h3>👥 مدیریت کاربران</h3>
          <div class="actions">
            <button on:click={() => { userAction='none'; userForm={ id:'', full_name:'', username:'', password:'', role:'user' } }}>📋 لیست کاربران</button>
            <button on:click={() => { userAction='create'; userForm={ id:'', full_name:'', username:'', password:'', role:'user' } }}>➕ ایجاد</button>
            <button on:click={() => (userAction='edit')} disabled={!userForm.id}>✏️ ویرایش</button>
            <button on:click={() => userForm.id && deleteUser(Number(userForm.id))} disabled={!userForm.id}>🗑️ حذف</button>
          </div>

          <div class="table-wrap">
            <table>
              <thead><tr><th>نام</th><th>نام کاربری</th><th>نقش</th></tr></thead>
              <tbody>
                {#each users as u}
                  <tr class:selected={String(u.id)===userForm.id} on:click={() => pickUser(u)}>
                    <td>{u.full_name}</td><td>{u.username}</td><td>{u.role}</td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>

          {#if userAction === 'create' || userAction === 'edit'}
            <div class="modal-backdrop" on:click={() => (userAction = 'none')}>
              <div class="modal" on:click|stopPropagation>
                <h4>{userAction === 'create' ? 'ایجاد کاربر' : 'ویرایش کاربر'}</h4>
                <div class="grid">
                  <label>نام <input bind:value={userForm.full_name} /></label>
                  <label>نام کاربری <input bind:value={userForm.username} disabled={userAction==='edit'} /></label>
                  <label>رمز عبور <input type="password" bind:value={userForm.password} placeholder={userAction==='edit' ? 'خالی = بدون تغییر' : ''} /></label>
                  <label>نقش
                    <select bind:value={userForm.role}><option value="user">user</option><option value="admin">admin</option></select>
                  </label>
                </div>
                <div class="modal-actions">
                  <button class="primary" on:click={saveUser}>💾 ذخیره کاربر</button>
                  <button on:click={() => (userAction = 'none')}>لغو</button>
                </div>
              </div>
            </div>
          {/if}
        </div>
      {/if}

      {#if view === 'projects' && currentUser.role === 'admin'}
        <div class="card">
          <h3>📁 مدیریت پروژه‌ها</h3>
          <div class="actions">
            <button on:click={() => { projectAction='list'; projectForm={ id:'', name:'', color:'#FC572C' } }}>📋 لیست پروژه‌ها</button>
            <button on:click={() => { projectAction='create'; projectForm={ id:'', name:'', color:'#FC572C' } }}>➕ ایجاد</button>
            <button on:click={() => (projectAction='edit')} disabled={!projectForm.id}>✏️ ویرایش</button>
            <button on:click={() => projectForm.id && deleteProject(Number(projectForm.id))} disabled={!projectForm.id}>🗑️ حذف</button>
          </div>
          <div class="table-wrap">
            <table>
              <thead><tr><th>نام پروژه</th><th>رنگ</th></tr></thead>
              <tbody>
                {#each projects as p}
                  <tr class:selected={String(p.id)===projectForm.id} on:click={() => pickProject(p)}>
                    <td>{p.name}</td><td><span class="dot" style={`background:${p.color}`}></span> {p.color}</td>
                  </tr>
                {/each}
              </tbody>
            </table>
          </div>

          {#if projectAction !== 'list'}
            <div class="grid">
              <label>نام پروژه <input bind:value={projectForm.name} /></label>
              <label>رنگ <input type="color" bind:value={projectForm.color} /></label>
            </div>
            <button class="primary" on:click={saveProject}>💾 ذخیره پروژه</button>
          {/if}
        </div>
      {/if}

      {#if view === 'report'}
        <div class="card">
          <h3>📊 گزارش‌گیری</h3>
          <div class="grid">
            {#if currentUser.role === 'admin'}
              <label>👤 کاربر
                <select bind:value={reportFilter.user_id}>
                  <option value="">همه</option>
                  {#each users as u}<option value={u.id}>{u.full_name}</option>{/each}
                </select>
              </label>
            {/if}
            <label>📁 پروژه
              <select bind:value={reportFilter.project_id}><option value="">همه</option>{#each projects as p}<option value={p.id}>{p.name}</option>{/each}</select>
            </label>
            <label>📅 از تاریخ شمسی <input bind:value={reportFilter.from_date_jalali} placeholder="1405/01/01" /></label>
            <label>📅 تا تاریخ شمسی <input bind:value={reportFilter.to_date_jalali} placeholder="1405/01/30" /></label>
          </div>
          <button class="primary" on:click={loadReport}>🔎 اعمال فیلتر</button>

          <div class="summary"><span>تعداد: {summary.entries_count}</span><span>کل ساعت: {summary.total_hours}</span></div>

          <h4>نمودار عمودی ساعت روزانه</h4>
          <div class="vchart">
            {#each dailyChart as d}
              <div class="vbar-col" title={`${d.work_date_j} - ${toHours(d.total_minutes)}h`}>
                <div class="vbar" style={`height:${Math.max(8, (Number(d.total_minutes) / maxDaily) * 180)}px`}></div>
                <small>{d.work_date_j.split('/').slice(1).join('/')}</small>
              </div>
            {/each}
          </div>

          <div class="table-wrap">
            <table>
              <thead><tr><th>تاریخ</th><th>کاربر</th><th>پروژه</th><th>شروع</th><th>پایان</th><th>دقیقه</th><th>شرح</th></tr></thead>
              <tbody>
                {#if reportDetails.length===0}
                  <tr><td colspan="7">داده‌ای وجود ندارد.</td></tr>
                {:else}
                  {#each reportDetails as row}
                    <tr><td>{row.work_date_j}</td><td>{row.user_name}</td><td>{row.project_name}</td><td>{row.start_time}</td><td>{row.end_time}</td><td>{row.duration_minutes}</td><td>{row.description}</td></tr>
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
  :global(body){margin:0;font-family:'IRANSansX',Tahoma,sans-serif;background:#fff7f3;color:#333;direction:rtl}
  .panel{display:grid;grid-template-columns:270px 1fr;min-height:100vh}
  .sidebar{background:linear-gradient(180deg,#fc572c,#d84a24);color:#fff;padding:16px;display:flex;flex-direction:column;gap:10px}
  .sidebar button{border:1px solid #ffffff4d;background:#ffffff24;color:#fff;padding:10px;border-radius:10px;text-align:right;cursor:pointer}
  .sidebar button.active{background:#fff;color:#fc572c;font-weight:800}
  .logout{margin-top:auto}
  .content{padding:20px}
  .card{background:#fff;border:1px solid #ffd9cc;border-radius:14px;padding:16px;box-shadow:0 8px 25px #fc572c1f}
  .auth-page{min-height:100vh;display:grid;place-items:center;padding:20px}
  .auth-card{width:min(420px,100%)}
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
  label{display:flex;flex-direction:column;gap:5px;margin-bottom:10px}
  input,select,textarea,button{font:inherit;border:1px solid #ffc7b6;border-radius:10px;padding:10px}
  .primary{background:#fc572c;border-color:#fc572c;color:#fff;cursor:pointer}
  .actions{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
  .table-wrap{overflow:auto;margin-top:10px}
  table{width:100%;border-collapse:collapse}
  th,td{border-bottom:1px solid #ffe2d8;padding:8px;text-align:right}
  tr.selected{background:#fff1eb}
  .alert{padding:10px;border-radius:10px;margin-bottom:10px}
  .success{background:#ffe4d8;color:#8d2c10}.error{background:#ffe8e8;color:#9e1f1f}
  .dot{display:inline-block;width:12px;height:12px;border-radius:50%}
  .summary{display:flex;gap:20px;margin:12px 0}
  .vchart{display:flex;gap:10px;align-items:flex-end;min-height:230px;max-width:100%;padding:10px;background:#fff4ef;border-radius:12px;overflow-x:auto;overflow-y:hidden}
  .vbar-col{display:flex;flex-direction:column;align-items:center;gap:6px;min-width:42px}
  .vbar{width:26px;background:#fc572c;border-radius:8px 8px 2px 2px}

  .modal-backdrop{position:fixed;inset:0;background:#00000055;display:grid;place-items:center;z-index:1000}
  .modal{width:min(680px,95vw);max-height:90vh;overflow:auto;background:#fff;border-radius:14px;padding:16px;border:1px solid #ffd9cc}
  .modal-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:8px}

</style>
