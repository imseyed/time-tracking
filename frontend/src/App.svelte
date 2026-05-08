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
  let entryEditOpen = false
  let entryEditForm = { id: 0, user_id: 0, project_id: 0, work_date_jalali: '', start_time: '', end_time: '', description: '' }
  let isLoggingIn = false
  let isSubmittingEntry = false
  let isSavingEntryEdit = false
  let isSavingUser = false
  let isSavingProject = false
  let isLoadingReport = false
  let reportReloadRequested = false
  let activeRequests = 0

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

  // Jalali conversion (based on jalaali-js algorithm)
  function div(a, b) { return Math.trunc(a / b) }
  function mod(a, b) { return a - div(a, b) * b }

  function g2d(gy, gm, gd) {
    let d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4) + div(153 * mod(gm + 9, 12) + 2, 5) + gd - 34840408
    d = d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752
    return d
  }

  function d2g(jdn) {
    let j = 4 * jdn + 139361631
    j = j + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908
    const i = div(mod(j, 1461), 4) * 5 + 308
    const gd = div(mod(i, 153), 5) + 1
    const gm = mod(div(i, 153), 12) + 1
    const gy = div(j, 1461) - 100100 + div(8 - gm, 6)
    return { gy, gm, gd }
  }

  function jalCal(jy) {
    const breaks = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178]
    const bl = breaks.length
    const gy = jy + 621
    let leapJ = -14
    let jp = breaks[0]
    let jm = 0
    let jump = 0
    let leap = 0
    let n = 0

    if (jy < jp || jy >= breaks[bl - 1]) throw new Error('Invalid Jalali year')

    for (let i = 1; i < bl; i += 1) {
      jm = breaks[i]
      jump = jm - jp
      if (jy < jm) break
      leapJ = leapJ + div(jump, 33) * 8 + div(mod(jump, 33), 4)
      jp = jm
    }

    n = jy - jp
    leapJ = leapJ + div(n, 33) * 8 + div(mod(n, 33) + 3, 4)
    if (mod(jump, 33) === 4 && jump - n === 4) leapJ += 1

    const leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150
    const march = 20 + leapJ - leapG

    if (jump - n < 6) n = n - jump + div(jump + 4, 33) * 33
    leap = mod(mod(n + 1, 33) - 1, 4)
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
    let k = jdn - jdn1f

    if (k >= 0) {
      if (k <= 185) return { jy, jm: 1 + div(k, 31), jd: mod(k, 31) + 1 }
      k -= 186
    } else {
      jy -= 1
      k += 179
      if (r.leap === 1) k += 1
    }

    return { jy, jm: 7 + div(k, 30), jd: mod(k, 30) + 1 }
  }

  const pad = (n) => String(n).padStart(2, '0')
  const jalaliMonthNames = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند']
  const jalaliWeekDays = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج']

  let calendarVisible = false
  let calendarTarget = ''
  let calendarView = { jy: 1405, jm: 1 }

  function getTodayGregorianString() {
    const today = new Date()
    return `${today.getFullYear()}-${pad(today.getMonth() + 1)}-${pad(today.getDate())}`
  }

  function getJalaliMonthLength(jy, jm) {
    if (jm >= 1 && jm <= 6) return 31
    if (jm >= 7 && jm <= 11) return 30
    if (jm === 12) return jalCal(jy).leap === 0 ? 30 : 29
    return 0
  }

  function parseJalaliDate(dateStr) {
    if (!dateStr) return null
    const m = String(dateStr).trim().match(/^(\d{3,4})[/-](\d{1,2})[/-](\d{1,2})$/)
    if (!m) return null
    const jy = Number(m[1])
    const jm = Number(m[2])
    const jd = Number(m[3])
    if (!Number.isInteger(jy) || !Number.isInteger(jm) || !Number.isInteger(jd)) return null
    if (jm < 1 || jm > 12) return null
    try {
      const maxDay = getJalaliMonthLength(jy, jm)
      if (jd < 1 || jd > maxDay) return null
    } catch {
      return null
    }
    return { jy, jm, jd }
  }

  function gregorianToJalali(dateStr) {
    if (!dateStr) return ''
    const m = String(dateStr).trim().match(/^(\d{4})-(\d{2})-(\d{2})$/)
    if (!m) return ''
    const gy = Number(m[1])
    const gm = Number(m[2])
    const gd = Number(m[3])
    if (!gy || gm < 1 || gm > 12 || gd < 1 || gd > 31) return ''
    try {
      const j = d2j(g2d(gy, gm, gd))
      return `${j.jy}/${pad(j.jm)}/${pad(j.jd)}`
    } catch {
      return ''
    }
  }

  function jalaliToGregorian(dateStr) {
    const p = parseJalaliDate(dateStr)
    if (!p) return ''
    try {
      const g = d2g(j2d(p.jy, p.jm, p.jd))
      return `${g.gy}-${pad(g.gm)}-${pad(g.gd)}`
    } catch {
      return ''
    }
  }

  function getCalendarTargetValue() {
    if (calendarTarget === 'entry') return form.work_date_jalali
    if (calendarTarget === 'edit') return entryEditForm.work_date_jalali
    if (calendarTarget === 'from') return reportFilter.from_date_jalali
    if (calendarTarget === 'to') return reportFilter.to_date_jalali
    return ''
  }

  function setCalendarTargetValue(value) {
    if (calendarTarget === 'entry') form.work_date_jalali = value
    if (calendarTarget === 'edit') entryEditForm.work_date_jalali = value
    if (calendarTarget === 'from') reportFilter.from_date_jalali = value
    if (calendarTarget === 'to') reportFilter.to_date_jalali = value
  }

  function openJalaliCalendar(target) {
    calendarTarget = target
    const targetDate = parseJalaliDate(getCalendarTargetValue()) || parseJalaliDate(gregorianToJalali(getTodayGregorianString()))
    if (targetDate) calendarView = { jy: targetDate.jy, jm: targetDate.jm }
    calendarVisible = true
  }

  function closeJalaliCalendar() {
    calendarVisible = false
    calendarTarget = ''
  }

  function prevCalendarMonth() {
    if (calendarView.jm === 1) calendarView = { jy: calendarView.jy - 1, jm: 12 }
    else calendarView = { ...calendarView, jm: calendarView.jm - 1 }
  }

  function nextCalendarMonth() {
    if (calendarView.jm === 12) calendarView = { jy: calendarView.jy + 1, jm: 1 }
    else calendarView = { ...calendarView, jm: calendarView.jm + 1 }
  }

  function getCalendarDays(jy, jm) {
    const daysInMonth = getJalaliMonthLength(jy, jm)
    const g = d2g(j2d(jy, jm, 1))
    const firstDay = new Date(g.gy, g.gm - 1, g.gd).getDay()
    const offset = (firstDay + 1) % 7
    const cells = Array(offset).fill(null)
    for (let day = 1; day <= daysInMonth; day += 1) cells.push(day)
    while (cells.length % 7 !== 0) cells.push(null)
    return cells
  }

  function selectCalendarDay(day) {
    if (!day) return
    const target = calendarTarget
    setCalendarTargetValue(`${calendarView.jy}/${pad(calendarView.jm)}/${pad(day)}`)
    closeJalaliCalendar()
    if (target === 'from' || target === 'to') {
      loadReport()
    }
  }

  function isSelectedCalendarDay(day) {
    if (!day) return false
    const p = parseJalaliDate(getCalendarTargetValue())
    if (!p) return false
    return p.jy === calendarView.jy && p.jm === calendarView.jm && p.jd === day
  }

  $: calendarDays = getCalendarDays(calendarView.jy, calendarView.jm)

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
  $: projectChart = Object.values(reportDetails.reduce((acc, row) => {
    const key = String(row.project_id ?? row.project_name ?? '')
    if (!key) return acc
    if (!acc[key]) {
      acc[key] = {
        project_id: row.project_id ?? null,
        project_name: row.project_name ?? 'بدون نام',
        project_color: row.project_color || '#FC572C',
        total_minutes: 0
      }
    }
    acc[key].total_minutes += Number(row.duration_minutes || 0)
    return acc
  }, {})).sort((a, b) => Number(b.total_minutes) - Number(a.total_minutes))
  $: maxProjectMinutes = Math.max(1, ...projectChart.map((p) => Number(p.total_minutes || 0)))
  $: userMenu = currentUser ? menuByRole[currentUser.role] ?? [] : []
  $: isBusy = activeRequests > 0 || isLoadingReport || isLoggingIn || isSubmittingEntry || isSavingEntryEdit || isSavingUser || isSavingProject

  function clearAlerts() { error = '' }

  function authParams() {
    return currentUser ? `&auth_user_id=${currentUser.id}` : ''
  }

  async function trackedFetch(url, options = undefined) {
    activeRequests += 1
    try {
      return await fetch(url, options)
    } finally {
      activeRequests = Math.max(0, activeRequests - 1)
    }
  }

  function moveSelectByArrowKey(event, list, currentValue, setter) {
    if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return
    if (!Array.isArray(list) || list.length === 0) return
    event.preventDefault()
    const ids = list.map((item) => String(item.id))
    let idx = ids.indexOf(String(currentValue))
    if (idx < 0) idx = 0
    idx = event.key === 'ArrowDown'
      ? (idx + 1) % ids.length
      : (idx - 1 + ids.length) % ids.length
    setter(ids[idx])
  }

  function onEntryProjectKeyDown(event) {
    moveSelectByArrowKey(event, projects, form.project_id, (next) => { form.project_id = next })
  }

  async function request(action, method = 'GET', payload = null) {
    const url = `${API_BASE}?action=${action}${method === 'GET' ? authParams() : ''}`
    const options = { method, headers: { 'Content-Type': 'application/json' } }
    if (method !== 'GET') options.body = JSON.stringify({ ...(payload || {}), auth_user_id: currentUser?.id })
    const res = await trackedFetch(url, options)
    const json = await res.json()
    if (!res.ok) throw new Error(json.error || 'خطا')
    return json
  }

  async function doLogin() {
    if (isLoggingIn) return
    isLoggingIn = true
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
    } finally {
      isLoggingIn = false
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
    if (!form.user_id || !users.some((u) => Number(u.id) === Number(form.user_id))) {
      form.user_id = String(currentUser.id)
    }
  }

  async function loadProjects() {
    const json = await request('projects')
    projects = json.data || []
    if (!form.project_id && projects.length) form.project_id = String(projects[0].id)
  }

  async function loadRecentEntries() {
    const qs = new URLSearchParams({ action: 'entries-recent', auth_user_id: String(currentUser.id) })
    if (currentUser.role === 'admin' && form.user_id) qs.set('user_id', form.user_id)
    const res = await trackedFetch(`${API_BASE}?${qs.toString()}`)
    const json = await res.json()
    if (!res.ok) throw new Error(json.error || 'لیست رکوردها دریافت نشد.')
    recentEntries = (json.data || []).map((r) => ({ ...r, work_date_j: gregorianToJalali(r.work_date) }))
  }

  async function refreshRecentEntries() {
    clearAlerts()
    try {
      await loadRecentEntries()
    } catch (e) {
      error = e.message
    }
  }

  async function loadReport() {
    if (isLoadingReport) {
      reportReloadRequested = true
      return
    }
    isLoadingReport = true
    clearAlerts()
    try {
      const qs = new URLSearchParams({ action: 'report', auth_user_id: String(currentUser.id) })
      if (reportFilter.user_id) qs.set('user_id', reportFilter.user_id)
      if (reportFilter.project_id) qs.set('project_id', reportFilter.project_id)
      const fromG = jalaliToGregorian(reportFilter.from_date_jalali)
      const toG = jalaliToGregorian(reportFilter.to_date_jalali)
      if (fromG) qs.set('from_date', fromG)
      if (toG) qs.set('to_date', toG)

      const res = await trackedFetch(`${API_BASE}?${qs.toString()}`)
      const json = await res.json()
      if (!res.ok) throw new Error(json.error || 'گزارش دریافت نشد.')

      reportDetails = (json.details || []).map((r) => ({ ...r, work_date_j: gregorianToJalali(r.work_date) }))
      dailyChart = (json.daily_chart || []).map((d) => ({ ...d, work_date_j: gregorianToJalali(d.work_date) }))
      summary = json.summary || summary
    } catch (e) {
      error = e.message
    } finally {
      isLoadingReport = false
      if (reportReloadRequested) {
        reportReloadRequested = false
        await loadReport()
      }
    }
  }

  function resolveUserIdForEdit(row) {
    const idFromRow = Number(row.user_id || 0)
    if (idFromRow > 0) return idFromRow
    const byName = users.find((u) => u.full_name === row.user_name)
    if (byName) return Number(byName.id)
    return Number(currentUser?.id || 0)
  }

  function resolveProjectIdForEdit(row) {
    const idFromRow = Number(row.project_id || 0)
    if (idFromRow > 0) return idFromRow
    const byName = projects.find((p) => p.name === row.project_name)
    if (byName) return Number(byName.id)
    return Number(projects[0]?.id || 0)
  }

  function openEntryEditDialog(row) {
    entryEditForm = {
      id: Number(row.id || 0),
      user_id: Number(resolveUserIdForEdit(row)),
      project_id: Number(resolveProjectIdForEdit(row)),
      work_date_jalali: row.work_date_j || gregorianToJalali(row.work_date),
      start_time: row.start_time || '',
      end_time: row.end_time || '',
      description: row.description || ''
    }
    entryEditOpen = true
  }

  function closeEntryEditDialog() {
    entryEditOpen = false
    entryEditForm = { id: 0, user_id: 0, project_id: 0, work_date_jalali: '', start_time: '', end_time: '', description: '' }
  }

  async function submitEntryEdit() {
    if (isSavingEntryEdit) return
    isSavingEntryEdit = true
    clearAlerts()
    try {
      const workDate = jalaliToGregorian(entryEditForm.work_date_jalali)
      if (!workDate) throw new Error('تاریخ شمسی معتبر نیست. مثال: 1405/01/31')
      const payload = {
        id: Number(entryEditForm.id),
        user_id: Number(entryEditForm.user_id || 0),
        project_id: Number(entryEditForm.project_id || 0),
        work_date: workDate,
        start_time: entryEditForm.start_time,
        end_time: entryEditForm.end_time,
        description: entryEditForm.description || ''
      }
      await request('entry-update', 'POST', payload)
      closeEntryEditDialog()
      await loadRecentEntries()
      await loadReport()
      message = '✅ رکورد ساعت کاری ویرایش شد.'
    } catch (e) {
      error = e.message
    } finally {
      isSavingEntryEdit = false
    }
  }

  async function deleteEntry(row) {
    const ok = confirm('آیا از حذف این رکورد مطمئن هستید؟')
    if (!ok) return
    clearAlerts()
    try {
      await request('entry-delete', 'POST', { id: Number(row.id) })
      await loadRecentEntries()
      await loadReport()
      message = '✅ رکورد ساعت کاری حذف شد.'
    } catch (e) {
      error = e.message
    }
  }

  async function submitEntry() {
    if (isSubmittingEntry) return
    isSubmittingEntry = true
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
      form.start_time = ''
      form.end_time = ''
      form.description = ''
      await loadRecentEntries()
      await loadReport()
      message = '✅ ساعت کاری با موفقیت ثبت شد.'
    } catch (e) {
      error = e.message
    } finally {
      isSubmittingEntry = false
    }
  }

  function pickUser(u) {
    userForm = { id: String(u.id), full_name: u.full_name, username: u.username, password: '', role: u.role }
    userAction = 'edit'
  }

  async function saveUser() {
    if (isSavingUser) return
    isSavingUser = true
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
    } catch (e) { error = e.message } finally { isSavingUser = false }
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
    if (isSavingProject) return
    isSavingProject = true
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
    } catch (e) { error = e.message } finally { isSavingProject = false }
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
      <form on:submit|preventDefault={doLogin}>
        <label>👤 نام کاربری <input bind:value={loginForm.username} /></label>
        <label>🔐 رمز عبور <input type="password" bind:value={loginForm.password} /></label>
        <button class="primary" type="submit" disabled={isLoggingIn}>🔓 ورود</button>
      </form>
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
          <form on:submit|preventDefault={submitEntry}>
            <div class="grid">
              {#if currentUser.role === 'admin'}
                <label>👤 کاربر
                  <select bind:value={form.user_id} on:change={refreshRecentEntries}>
                    {#each users as u}<option value={u.id}>{u.full_name}</option>{/each}
                  </select>
                </label>
              {/if}
              <label>📁 پروژه
                <select bind:value={form.project_id} on:keydown={onEntryProjectKeyDown}>
                  {#each projects as p}<option value={p.id}>{p.name}</option>{/each}
                </select>
              </label>
              <label>📅 تاریخ شمسی (yyyy/mm/dd)
                <input
                  bind:value={form.work_date_jalali}
                  placeholder="1405/01/31"
                  on:focus={() => openJalaliCalendar('entry')}
                  on:click={() => openJalaliCalendar('entry')}
                />
              </label>
              <label>🕒 شروع <input type="time" bind:value={form.start_time} /></label>
              <label>🕕 پایان <input type="time" bind:value={form.end_time} /></label>
            </div>
            <label>📝 شرح کار <textarea rows="3" bind:value={form.description} /></label>
            <button class="primary" type="submit" disabled={isSubmittingEntry}>💾 ثبت ساعت</button>
          </form>

          <h4>25 رکورد اخیر</h4>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>تاریخ</th>
                  {#if currentUser.role === 'admin'}<th>کاربر</th>{/if}
                  <th>پروژه</th><th>شروع</th><th>پایان</th><th>دقیقه</th><th>شرح</th><th>عملیات</th>
                </tr>
              </thead>
              <tbody>
                {#if recentEntries.length === 0}
                  <tr><td colspan={currentUser.role === 'admin' ? 8 : 7}>رکوردی ثبت نشده است.</td></tr>
                {:else}
                  {#each recentEntries as row}
                    <tr>
                      <td>{row.work_date_j}</td>
                      {#if currentUser.role === 'admin'}<td>{row.user_name}</td>{/if}
                      <td>{row.project_name}</td><td>{row.start_time}</td><td>{row.end_time}</td><td>{row.duration_minutes}</td><td>{row.description}</td>
                      <td>
                        <div class="row-actions">
                          <button on:click={() => openEntryEditDialog(row)}>✏️ ویرایش</button>
                          <button class="danger" on:click={() => deleteEntry(row)}>🗑️ حذف</button>
                        </div>
                      </td>
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
                <form on:submit|preventDefault={saveUser}>
                  <div class="grid">
                    <label>نام <input bind:value={userForm.full_name} /></label>
                    <label>نام کاربری <input bind:value={userForm.username} disabled={userAction==='edit'} /></label>
                    <label>رمز عبور <input type="password" bind:value={userForm.password} placeholder={userAction==='edit' ? 'خالی = بدون تغییر' : ''} /></label>
                    <label>نقش
                      <select bind:value={userForm.role}><option value="user">user</option><option value="admin">admin</option></select>
                    </label>
                  </div>
                  <div class="modal-actions">
                    <button class="primary" type="submit" disabled={isSavingUser}>💾 ذخیره کاربر</button>
                    <button type="button" on:click={() => (userAction = 'none')}>لغو</button>
                  </div>
                </form>
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
            <form on:submit|preventDefault={saveProject}>
              <div class="grid">
                <label>نام پروژه <input bind:value={projectForm.name} /></label>
                <label>رنگ <input type="color" bind:value={projectForm.color} /></label>
              </div>
              <button class="primary" type="submit" disabled={isSavingProject}>💾 ذخیره پروژه</button>
            </form>
          {/if}
        </div>
      {/if}

      {#if view === 'report'}
        <div class="card">
          <h3>📊 گزارش‌گیری</h3>
          <form on:submit|preventDefault={loadReport}>
            <div class="grid">
              {#if currentUser.role === 'admin'}
                <label>👤 کاربر
                  <select bind:value={reportFilter.user_id} on:change={loadReport}>
                    <option value="">همه</option>
                    {#each users as u}<option value={u.id}>{u.full_name}</option>{/each}
                  </select>
                </label>
              {/if}
              <label>📁 پروژه
                <select bind:value={reportFilter.project_id} on:change={loadReport}><option value="">همه</option>{#each projects as p}<option value={p.id}>{p.name}</option>{/each}</select>
              </label>
              <label>📅 از تاریخ شمسی
                <input
                  bind:value={reportFilter.from_date_jalali}
                  placeholder="1405/01/01"
                  on:focus={() => openJalaliCalendar('from')}
                  on:click={() => openJalaliCalendar('from')}
                  on:blur={loadReport}
                />
              </label>
              <label>📅 تا تاریخ شمسی
                <input
                  bind:value={reportFilter.to_date_jalali}
                  placeholder="1405/01/30"
                  on:focus={() => openJalaliCalendar('to')}
                  on:click={() => openJalaliCalendar('to')}
                  on:blur={loadReport}
                />
              </label>
            </div>
            <button class="primary" type="submit" disabled={isLoadingReport}>🔎 اعمال فیلتر</button>
          </form>

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

          <h4>نمودار افقی ساعت به تفکیک پروژه</h4>
          <div class="hchart">
            {#if projectChart.length === 0}
              <div class="hchart-empty">داده‌ای برای نمودار پروژه وجود ندارد.</div>
            {:else}
              {#each projectChart as p}
                <div class="hbar-row" title={`${p.project_name} - ${toHours(p.total_minutes)}h`}>
                  <div class="hbar-label">{p.project_name}</div>
                  <div class="hbar-track">
                    <div
                      class="hbar-line"
                      style={`background:${p.project_color};width:${Math.max(6, (Number(p.total_minutes) / maxProjectMinutes) * 100)}%`}
                    ></div>
                  </div>
                  <div class="hbar-value">{toHours(p.total_minutes)} ساعت</div>
                </div>
              {/each}
            {/if}
          </div>

          <div class="table-wrap">
            <table>
              <thead><tr><th>تاریخ</th><th>کاربر</th><th>پروژه</th><th>شروع</th><th>پایان</th><th>دقیقه</th><th>شرح</th><th>عملیات</th></tr></thead>
              <tbody>
                {#if reportDetails.length===0}
                  <tr><td colspan="8">داده‌ای وجود ندارد.</td></tr>
                {:else}
                  {#each reportDetails as row}
                    <tr>
                      <td>{row.work_date_j}</td><td>{row.user_name}</td><td>{row.project_name}</td><td>{row.start_time}</td><td>{row.end_time}</td><td>{row.duration_minutes}</td><td>{row.description}</td>
                      <td>
                        <div class="row-actions">
                          <button on:click={() => openEntryEditDialog(row)}>✏️ ویرایش</button>
                          <button class="danger" on:click={() => deleteEntry(row)}>🗑️ حذف</button>
                        </div>
                      </td>
                    </tr>
                  {/each}
                {/if}
              </tbody>
            </table>
          </div>
        </div>
      {/if}
    </section>
  </main>

  {#if entryEditOpen}
    <div class="modal-backdrop" on:click={closeEntryEditDialog}>
      <div class="modal" on:click|stopPropagation>
        <h4>ویرایش رکورد ساعت کاری</h4>
        <form on:submit|preventDefault={submitEntryEdit}>
          <div class="grid">
            {#if currentUser.role === 'admin'}
              <label>👤 کاربر
                <select bind:value={entryEditForm.user_id}>
                  {#each users as u}<option value={u.id}>{u.full_name}</option>{/each}
                </select>
              </label>
            {/if}
            <label>📁 پروژه
              <select bind:value={entryEditForm.project_id}>
                {#each projects as p}<option value={p.id}>{p.name}</option>{/each}
              </select>
            </label>
            <label>📅 تاریخ شمسی (yyyy/mm/dd)
              <input
                bind:value={entryEditForm.work_date_jalali}
                placeholder="1405/01/31"
                on:focus={() => openJalaliCalendar('edit')}
                on:click={() => openJalaliCalendar('edit')}
              />
            </label>
            <label>🕒 شروع <input type="time" bind:value={entryEditForm.start_time} /></label>
            <label>🕕 پایان <input type="time" bind:value={entryEditForm.end_time} /></label>
          </div>
          <label>📝 شرح کار <textarea rows="3" bind:value={entryEditForm.description} /></label>
          <div class="modal-actions">
            <button class="primary" type="submit" disabled={isSavingEntryEdit}>💾 ذخیره ویرایش</button>
            <button type="button" on:click={closeEntryEditDialog}>لغو</button>
          </div>
        </form>
      </div>
    </div>
  {/if}

  {#if calendarVisible}
    <div class="calendar-backdrop" on:click={closeJalaliCalendar}>
      <div class="calendar-popup" on:click|stopPropagation>
        <div class="calendar-head">
          <button type="button" on:click={nextCalendarMonth}>‹</button>
          <strong>{jalaliMonthNames[calendarView.jm - 1]} {calendarView.jy}</strong>
          <button type="button" on:click={prevCalendarMonth}>›</button>
        </div>
        <div class="calendar-grid weekdays">
          {#each jalaliWeekDays as wd}<span>{wd}</span>{/each}
        </div>
        <div class="calendar-grid days">
          {#each calendarDays as d}
            <button
              type="button"
              class:empty={!d}
              class:selected={isSelectedCalendarDay(d)}
              disabled={!d}
              on:click={() => selectCalendarDay(d)}
            >
              {d ?? ''}
            </button>
          {/each}
        </div>
      </div>
    </div>
  {/if}

  {#if isBusy}
    <div class="busy-overlay">
      <div class="busy-spinner"></div>
      <div>در حال پردازش...</div>
    </div>
  {/if}
{/if}

<style>
  @font-face { font-family: 'IRANSansX'; src: url('/fonts/IRANSansXFaNum-Medium.woff2') format('woff2'); font-weight: 500; }
  @font-face { font-family: 'IRANSansX'; src: url('/fonts/IRANSansXFaNum-ExtraBold.woff2') format('woff2'); font-weight: 800; }
  :global(body){margin:0;font-family:'IRANSansX',Tahoma,sans-serif;background:#fff7f3;color:#333;direction:rtl}
  .panel{display:grid;grid-template-columns:270px 1fr;min-height:100vh}
  .sidebar{background:linear-gradient(180deg,#fc572c,#d84a24);color:#fff;padding:16px;display:flex;flex-direction:column;gap:10px}
  .sidebar nav{display:flex;flex-direction:column;gap:8px}
  .sidebar button{border:1px solid #ffffff4d;background:#ffffff24;color:#fff;padding:10px;border-radius:10px;text-align:right;cursor:pointer}
  .sidebar nav button{width:100%;display:block}
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
  .row-actions{display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap}
  .row-actions button{padding:6px 8px;font-size:.9rem}
  .danger{background:#ffe5e5;border-color:#f0b6b6;color:#9e1f1f}
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
  .hchart{display:flex;flex-direction:column;gap:10px;background:#fff4ef;border-radius:12px;padding:12px;margin-top:10px;margin-bottom:12px}
  .hchart-empty{color:#8a4a36}
  .hbar-row{display:grid;grid-template-columns:minmax(130px,210px) 1fr minmax(80px,110px);gap:10px;align-items:center}
  .hbar-label{font-size:.95rem;color:#663120}
  .hbar-track{background:#ffe2d8;border-radius:999px;height:14px;overflow:hidden}
  .hbar-line{height:100%;border-radius:999px;transition:width .2s ease}
  .hbar-value{text-align:left;font-size:.9rem;color:#8d2c10}

  .modal-backdrop{position:fixed;inset:0;background:#00000055;display:grid;place-items:center;z-index:1000}
  .modal{width:min(680px,95vw);max-height:90vh;overflow:auto;background:#fff;border-radius:14px;padding:16px;border:1px solid #ffd9cc}
  .modal-actions{display:flex;gap:8px;justify-content:flex-end;margin-top:8px}

  .calendar-backdrop{position:fixed;inset:0;background:#0000003d;display:grid;place-items:center;z-index:1200}
  .calendar-popup{background:#fff;border:1px solid #ffd9cc;border-radius:14px;padding:12px;width:min(320px,92vw);box-shadow:0 14px 30px #00000020}
  .calendar-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
  .calendar-head button{width:36px;height:36px;padding:0;border-radius:10px;background:#fff4ef;color:#a33f20;border:1px solid #ffc7b6;cursor:pointer}
  .calendar-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:6px}
  .calendar-grid.weekdays span{display:grid;place-items:center;font-size:.85rem;color:#8a4a36}
  .calendar-grid.days button{height:34px;border:1px solid #ffd8cb;background:#fff;border-radius:8px;cursor:pointer}
  .calendar-grid.days button.selected{background:#fc572c;color:#fff;border-color:#fc572c}
  .calendar-grid.days button.empty{visibility:hidden}
  .calendar-grid.days button:disabled{cursor:default}

  .busy-overlay{position:fixed;inset:0;z-index:2200;background:rgba(0,0,0,.3);display:grid;place-items:center;gap:10px;color:#fff;font-weight:700}
  .busy-spinner{width:42px;height:42px;border-radius:50%;border:4px solid #ffffff80;border-top-color:#fff;animation:spin .8s linear infinite}
  @keyframes spin{to{transform:rotate(360deg)}}

</style>
