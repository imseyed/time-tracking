<script>
  import { onMount } from 'svelte'

  const API_BASE = 'http://localhost:8080/api.php'

  let currentUser = null
  let view = 'entry'

  let loginForm = { username: 'admin', password: 'public' }

  let users = []
  let projects = []
  let reportDetails = []
  let chartData = []
  let summary = { entries_count: 0, total_hours: 0 }

  let form = {
    user_id: '',
    project_id: '',
    work_date: new Date().toISOString().slice(0, 10),
    start_time: '',
    end_time: '',
    description: ''
  }

  let reportFilter = { user_id: '', project_id: '', from_date: '', to_date: '' }
  let userForm = { full_name: '', username: '', password: '', role: 'user' }
  let editUser = { id: '', full_name: '', role: 'user', password: '' }
  let projectForm = { name: '', color: '#FC572C' }

  let message = ''
  let error = ''

  const menuByRole = {
    admin: [
      { key: 'entry', label: 'ثبت ساعت' },
      { key: 'report', label: 'گزارش‌گیری همه' },
      { key: 'users', label: 'مدیریت کاربران' },
      { key: 'projects', label: 'مدیریت پروژه‌ها' }
    ],
    user: [
      { key: 'entry', label: 'ثبت ساعت' },
      { key: 'report', label: 'گزارش من' }
    ]
  }

  const toHours = (m) => (Number(m) / 60).toFixed(2)
  $: maxChart = Math.max(1, ...chartData.map((d) => Number(d.total_minutes || 0)))
  $: userMenu = currentUser ? menuByRole[currentUser.role] ?? [] : []

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

    if (method !== 'GET') {
      options.body = JSON.stringify({ ...(payload || {}), auth_user_id: currentUser?.id })
    }

    const res = await fetch(url, options)
    const json = await res.json()
    if (!res.ok) throw new Error(json.error || 'خطا در ارتباط با سرور.')
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
    view = 'entry'
    clearAlerts()
  }

  async function loadUsers() {
    if (!currentUser) return
    const json = await request('users')
    users = json.data || []
    if (currentUser.role !== 'admin') {
      users = users.filter((u) => u.id === currentUser.id)
    }
  }

  async function loadProjects() {
    if (!currentUser) return
    const json = await request('projects')
    projects = json.data || []
    if (!form.project_id && projects.length) form.project_id = String(projects[0].id)
  }

  async function loadReport() {
    clearAlerts()
    try {
      const qs = new URLSearchParams({ action: 'report', auth_user_id: String(currentUser.id) })
      if (reportFilter.user_id) qs.set('user_id', reportFilter.user_id)
      if (reportFilter.project_id) qs.set('project_id', reportFilter.project_id)
      if (reportFilter.from_date) qs.set('from_date', reportFilter.from_date)
      if (reportFilter.to_date) qs.set('to_date', reportFilter.to_date)

      const res = await fetch(`${API_BASE}?${qs.toString()}`)
      const json = await res.json()
      if (!res.ok) throw new Error(json.error || 'گزارش دریافت نشد.')

      reportDetails = json.details || []
      chartData = json.chart || []
      summary = json.summary || summary
    } catch (e) {
      error = e.message
    }
  }

  async function submitEntry() {
    clearAlerts()
    try {
      await request('entry-create', 'POST', {
        ...form,
        user_id: Number(form.user_id),
        project_id: Number(form.project_id)
      })
      message = 'ساعت کاری با موفقیت ثبت شد.'
      form.start_time = ''
      form.end_time = ''
      form.description = ''
      await loadReport()
    } catch (e) {
      error = e.message
    }
  }

  async function createUser() {
    clearAlerts()
    try {
      await request('user-create', 'POST', userForm)
      message = 'کاربر ایجاد شد.'
      userForm = { full_name: '', username: '', password: '', role: 'user' }
      await loadUsers()
    } catch (e) {
      error = e.message
    }
  }

  async function updateUser() {
    clearAlerts()
    try {
      await request('user-update', 'POST', editUser)
      message = 'اطلاعات کاربر ویرایش شد.'
      editUser = { id: '', full_name: '', role: 'user', password: '' }
      await loadUsers()
    } catch (e) {
      error = e.message
    }
  }

  async function createProject() {
    clearAlerts()
    try {
      await request('project-create', 'POST', projectForm)
      message = 'پروژه جدید ایجاد شد.'
      projectForm = { name: '', color: '#FC572C' }
      await loadProjects()
    } catch (e) {
      error = e.message
    }
  }

  async function loadAppData() {
    await loadProjects()
    if (currentUser?.role === 'admin') {
      await loadUsers()
    } else {
      users = [currentUser]
    }
    await loadReport()
  }

  onMount(async () => {
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
    <section class="auth-card">
      <h1>ورود به سامانه ثبت ساعت</h1>
      <p>اکانت پیش‌فرض مدیر: <strong>admin / public</strong></p>
      {#if error}<div class="alert error">{error}</div>{/if}
      <label>نام کاربری <input bind:value={loginForm.username} /></label>
      <label>رمز عبور <input type="password" bind:value={loginForm.password} /></label>
      <button class="primary" on:click={doLogin}>ورود</button>
    </section>
  </main>
{:else}
  <main class="panel">
    <aside class="sidebar">
      <div class="brand">
        <h2>پنل زمان‌سنجی</h2>
        <small>{currentUser.full_name} ({currentUser.role})</small>
      </div>

      <nav>
        {#each userMenu as item}
          <button class:active={view === item.key} on:click={() => (view = item.key)}>{item.label}</button>
        {/each}
      </nav>

      <button class="logout" on:click={logout}>خروج</button>
    </aside>

    <section class="content">
      {#if message}<div class="alert success">{message}</div>{/if}
      {#if error}<div class="alert error">{error}</div>{/if}

      {#if view === 'entry'}
        <div class="card">
          <h3>ثبت ساعت کاری</h3>
          <div class="grid">
            {#if currentUser.role === 'admin'}
              <label>کاربر
                <select bind:value={form.user_id}>
                  {#each users as u}<option value={u.id}>{u.full_name}</option>{/each}
                </select>
              </label>
            {/if}
            <label>پروژه
              <select bind:value={form.project_id}>
                {#each projects as p}<option value={p.id}>{p.name}</option>{/each}
              </select>
            </label>
            <label>تاریخ <input type="date" bind:value={form.work_date} /></label>
            <label>شروع <input type="time" bind:value={form.start_time} /></label>
            <label>پایان <input type="time" bind:value={form.end_time} /></label>
          </div>
          <label>شرح کار <textarea rows="4" bind:value={form.description}></textarea></label>
          <button class="primary" on:click={submitEntry}>ثبت</button>
        </div>
      {/if}

      {#if view === 'report'}
        <div class="card">
          <h3>{currentUser.role === 'admin' ? 'گزارش‌گیری همه کاربران' : 'گزارش‌گیری من'}</h3>
          <div class="grid">
            {#if currentUser.role === 'admin'}
              <label>کاربر
                <select bind:value={reportFilter.user_id}>
                  <option value="">همه</option>
                  {#each users as u}<option value={u.id}>{u.full_name}</option>{/each}
                </select>
              </label>
            {/if}
            <label>پروژه
              <select bind:value={reportFilter.project_id}>
                <option value="">همه</option>
                {#each projects as p}<option value={p.id}>{p.name}</option>{/each}
              </select>
            </label>
            <label>از تاریخ <input type="date" bind:value={reportFilter.from_date} /></label>
            <label>تا تاریخ <input type="date" bind:value={reportFilter.to_date} /></label>
          </div>
          <button class="primary" on:click={loadReport}>اعمال فیلتر</button>

          <div class="summary">
            <span>تعداد رکورد: {summary.entries_count}</span>
            <span>جمع ساعت: {summary.total_hours}</span>
          </div>

          <h4>نمودار پروژه‌ها</h4>
          {#each chartData as item}
            <div class="bar-row">
              <span>{item.project_name} ({toHours(item.total_minutes)}h)</span>
              <div class="bar-track"><div class="bar-fill" style={`width:${(Number(item.total_minutes) / maxChart) * 100}%;background:${item.project_color}`}></div></div>
            </div>
          {/each}

          <div class="table-wrap">
            <table>
              <thead>
                <tr><th>تاریخ</th><th>کاربر</th><th>پروژه</th><th>شروع</th><th>پایان</th><th>دقیقه</th><th>شرح</th></tr>
              </thead>
              <tbody>
                {#if reportDetails.length === 0}
                  <tr><td colspan="7">داده‌ای وجود ندارد.</td></tr>
                {:else}
                  {#each reportDetails as row}
                    <tr>
                      <td>{row.work_date}</td>
                      <td>{row.user_name}</td>
                      <td>{row.project_name}</td>
                      <td>{row.start_time}</td>
                      <td>{row.end_time}</td>
                      <td>{row.duration_minutes}</td>
                      <td>{row.description}</td>
                    </tr>
                  {/each}
                {/if}
              </tbody>
            </table>
          </div>
        </div>
      {/if}

      {#if view === 'users' && currentUser.role === 'admin'}
        <div class="card two-col">
          <div>
            <h3>ایجاد کاربر</h3>
            <label>نام <input bind:value={userForm.full_name} /></label>
            <label>نام کاربری <input bind:value={userForm.username} /></label>
            <label>رمز عبور <input type="password" bind:value={userForm.password} /></label>
            <label>نقش
              <select bind:value={userForm.role}><option value="user">کاربر</option><option value="admin">ادمین</option></select>
            </label>
            <button class="primary" on:click={createUser}>ایجاد کاربر</button>
          </div>
          <div>
            <h3>ویرایش کاربر</h3>
            <label>انتخاب کاربر
              <select bind:value={editUser.id}>
                <option value="">انتخاب کنید</option>
                {#each users as u}<option value={u.id}>{u.full_name} ({u.username})</option>{/each}
              </select>
            </label>
            <label>نام جدید <input bind:value={editUser.full_name} /></label>
            <label>نقش
              <select bind:value={editUser.role}><option value="user">کاربر</option><option value="admin">ادمین</option></select>
            </label>
            <label>رمز جدید (اختیاری) <input type="password" bind:value={editUser.password} /></label>
            <button class="primary" on:click={updateUser}>ویرایش کاربر</button>
          </div>
        </div>
      {/if}

      {#if view === 'projects' && currentUser.role === 'admin'}
        <div class="card">
          <h3>ایجاد پروژه (ادمین)</h3>
          <div class="grid">
            <label>نام پروژه <input bind:value={projectForm.name} /></label>
            <label>رنگ <input type="color" bind:value={projectForm.color} /></label>
          </div>
          <button class="primary" on:click={createProject}>ایجاد پروژه</button>

          <ul class="projects-list">
            {#each projects as p}
              <li><span class="dot" style={`background:${p.color}`}></span>{p.name}</li>
            {/each}
          </ul>
        </div>
      {/if}
    </section>
  </main>
{/if}

<style>
  @font-face {
    font-family: 'IRANSansX';
    src: url('/fonts/IRANSansXFaNum-Medium.woff2') format('woff2');
    font-weight: 500;
    font-style: normal;
  }

  @font-face {
    font-family: 'IRANSansX';
    src: url('/fonts/IRANSansXFaNum-ExtraBold.woff') format('woff');
    font-weight: 800;
    font-style: normal;
  }

  :global(body) {
    margin: 0;
    font-family: 'IRANSansX', Tahoma, sans-serif;
    background: #fff7f3;
    color: #333;
    direction: rtl;
  }

  .auth-page {
    min-height: 100vh;
    display: grid;
    place-items: center;
    padding: 20px;
  }

  .auth-card,
  .card {
    background: #fff;
    border: 1px solid #ffd7cb;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 8px 30px rgba(252, 87, 44, 0.12);
  }

  .auth-card { width: min(450px, 100%); }
  .auth-card h1 { margin-top: 0; font-weight: 800; color: #fc572c; }

  .panel {
    min-height: 100vh;
    display: grid;
    grid-template-columns: 290px 1fr;
  }

  .sidebar {
    background: linear-gradient(180deg, #fc572c, #e84a24);
    color: #fff;
    padding: 22px 16px;
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .brand h2 { margin: 0; font-weight: 800; }

  nav { display: grid; gap: 8px; }

  nav button,
  .logout {
    width: 100%;
    text-align: right;
    border: 1px solid rgba(255,255,255,0.35);
    background: rgba(255,255,255,0.15);
    color: #fff;
    border-radius: 12px;
    padding: 10px;
    cursor: pointer;
  }

  nav button.active { background: #fff; color: #fc572c; font-weight: 800; }

  .logout { margin-top: auto; }

  .content { padding: 24px; }

  .grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(190px,1fr)); gap: 12px; }
  .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }

  label { display: flex; flex-direction: column; gap: 6px; margin-bottom: 10px; }
  input, select, textarea, button {
    font: inherit;
    border: 1px solid #ffc7b6;
    border-radius: 10px;
    padding: 10px;
  }
  .primary {
    background: #fc572c;
    border-color: #fc572c;
    color: white;
    cursor: pointer;
  }
  .alert { margin-bottom: 10px; padding: 10px; border-radius: 10px; }
  .success { background: #ffe7de; color: #8f2a10; }
  .error { background: #ffe3e3; color: #a02727; }

  .summary { margin: 10px 0 16px; display: flex; gap: 20px; }
  .bar-row { margin-bottom: 10px; }
  .bar-track { background: #ffeae2; border-radius: 7px; overflow: hidden; height: 16px; margin-top: 6px; }
  .bar-fill { height: 100%; }

  .table-wrap { overflow-x: auto; margin-top: 12px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { border-bottom: 1px solid #ffe1d7; text-align: right; padding: 8px; }

  .projects-list { list-style: none; padding: 0; margin: 14px 0 0; }
  .projects-list li { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
  .dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; }
</style>
