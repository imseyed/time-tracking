<script>
  import { onMount } from 'svelte'
  import AlertStack from './components/AlertStack.svelte'
  import AuthView from './components/AuthView.svelte'
  import BusyOverlay from './components/BusyOverlay.svelte'
  import EntryEditDialog from './components/EntryEditDialog.svelte'
  import EntrySection from './components/EntrySection.svelte'
  import JalaliCalendarDialog from './components/JalaliCalendarDialog.svelte'
  import PasswordDialog from './components/PasswordDialog.svelte'
  import ProjectsSection from './components/ProjectsSection.svelte'
  import ReportSection from './components/ReportSection.svelte'
  import SidebarNav from './components/SidebarNav.svelte'
  import ThemeDialog from './components/ThemeDialog.svelte'
  import UsersSection from './components/UsersSection.svelte'
  import {
    formatJalaliDateWithWeekday,
    getCalendarDays,
    getDefaultReportRange,
    getJalaliDayLabel,
    getTodayGregorianString,
    gregorianToJalali,
    jalaliMonthNames,
    jalaliToGregorian,
    jalaliWeekDays,
    parseJalaliDate,
  } from './lib/jalali.js'

  let API_BASE = 'http://localhost:8080/api.php' // fallback if config.js is missing

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
  let themeDialogOpen = false
  let themePreference = 'system'
  let passwordDialogOpen = false
  let passwordForm = { current_password: '', new_password: '', confirm_password: '' }
  let systemThemeMediaQuery = null
  let isLoggingIn = false
  let isSubmittingEntry = false
  let isSavingEntryEdit = false
  let isSavingUser = false
  let isSavingProject = false
  let isChangingPassword = false
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

  const pad = (n) => String(n).padStart(2, '0')

  let calendarVisible = false
  let calendarTarget = ''
  let calendarView = { jy: 1405, jm: 1 }

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
    const range = getDefaultReportRange(29)
    reportFilter.from_date_jalali = range.fromDateJalali
    reportFilter.to_date_jalali = range.toDateJalali
    form.work_date_jalali = range.todayJalali
  }

  const toHours = (m) => (Number(m) / 60).toFixed(2)

  function formatMinutesAsHHMM(minutes) {
    const n = Number(minutes)
    if (!Number.isFinite(n)) return '-'
    const sign = n < 0 ? '-' : ''
    const total = Math.abs(Math.round(n))
    const hours = Math.floor(total / 60)
    const mins = total % 60
    return `${sign}${pad(hours)}:${pad(mins)}`
  }
  const THEME_STORAGE_KEY = 'tt_theme_mode'

  function formatTimeToHHMM(timeValue) {
    if (!timeValue) return '-'
    const m = String(timeValue).trim().match(/^(\d{1,2}):(\d{2})/)
    if (!m) return String(timeValue)
    return `${pad(Number(m[1]))}:${m[2]}`
  }

  function getProjectColor(row) {
    if (row?.project_color) return row.project_color
    const rowProjectId = Number(row?.project_id || 0)
    if (rowProjectId > 0) {
      const byId = projects.find((p) => Number(p.id) === rowProjectId)
      if (byId?.color) return byId.color
    }
    const rowProjectName = String(row?.project_name || '').trim()
    if (rowProjectName) {
      const byName = projects.find((p) => p.name === rowProjectName)
      if (byName?.color) return byName.color
    }
    return '#FC572C'
  }

  function getProjectPillStyle(row) {
    const color = getProjectColor(row)
    return `background:${color}1a;border-color:${color};color:${color};`
  }
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
  $: isBusy = activeRequests > 0 || isLoadingReport || isLoggingIn || isSubmittingEntry || isSavingEntryEdit || isSavingUser || isSavingProject || isChangingPassword

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

  function closeOpenDialogsByEscape(event) {
    if (event.key !== 'Escape') return
    if (calendarVisible) closeJalaliCalendar()
    if (entryEditOpen) closeEntryEditDialog()
    if (passwordDialogOpen) closePasswordDialog()
    if (userAction === 'create' || userAction === 'edit') userAction = 'none'
    if (themeDialogOpen) themeDialogOpen = false
  }

  function resolveThemeMode(mode) {
    if (mode === 'dark') return 'dark'
    if (mode === 'light') return 'light'
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
  }

  function applyTheme(mode) {
    const resolvedMode = resolveThemeMode(mode)
    document.documentElement.setAttribute('data-theme', resolvedMode)
  }

  function saveThemePreference() {
    localStorage.setItem(THEME_STORAGE_KEY, themePreference)
    applyTheme(themePreference)
    themeDialogOpen = false
    message = '✅ تم به‌روزرسانی شد.'
  }

  function chooseTheme(mode) {
    if (!['light', 'dark', 'system'].includes(mode)) return
    themePreference = mode
    saveThemePreference()
  }

  function loadSavedThemePreference() {
    const saved = localStorage.getItem(THEME_STORAGE_KEY)
    if (saved === 'light' || saved === 'dark' || saved === 'system') themePreference = saved
    else themePreference = 'system'
    applyTheme(themePreference)
  }

  function onSystemThemeChange() {
    if (themePreference === 'system') applyTheme('system')
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
    closePasswordDialog()
    message = ''
    error = ''
  }

  function openPasswordDialog() {
    passwordForm = { current_password: '', new_password: '', confirm_password: '' }
    passwordDialogOpen = true
  }

  function closePasswordDialog() {
    passwordDialogOpen = false
    passwordForm = { current_password: '', new_password: '', confirm_password: '' }
  }

  async function changePassword() {
    if (isChangingPassword) return
    isChangingPassword = true
    clearAlerts()
    try {
      if (!passwordForm.current_password || !passwordForm.new_password || !passwordForm.confirm_password) {
        throw new Error('تمام فیلدهای تغییر رمز الزامی است.')
      }
      if (passwordForm.new_password.length < 4) {
        throw new Error('رمز جدید باید حداقل 4 کاراکتر باشد.')
      }
      if (passwordForm.new_password !== passwordForm.confirm_password) {
        throw new Error('رمز جدید و تکرار آن یکسان نیست.')
      }
      await request('change-password', 'POST', passwordForm)
      closePasswordDialog()
      message = '✅ رمز عبور با موفقیت تغییر کرد.'
    } catch (e) {
      error = e.message
    } finally {
      isChangingPassword = false
    }
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
      dailyChart = (json.daily_chart || []).map((d) => ({
        work_date: d.work_date,
        total_minutes: Number(d.total_minutes || 0),
        work_date_j: gregorianToJalali(d.work_date)
      }))
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

  function openUserList() {
    userAction = 'none'
    userForm = { id: '', full_name: '', username: '', password: '', role: 'user' }
  }

  function openUserCreate() {
    userAction = 'create'
    userForm = { id: '', full_name: '', username: '', password: '', role: 'user' }
  }

  function openUserEdit() {
    userAction = 'edit'
  }

  function closeUserModal() {
    userAction = 'none'
  }

  function openProjectList() {
    projectAction = 'list'
    projectForm = { id: '', name: '', color: '#FC572C' }
  }

  function openProjectCreate() {
    projectAction = 'create'
    projectForm = { id: '', name: '', color: '#FC572C' }
  }

  function openProjectEdit() {
    projectAction = 'edit'
  }

  async function loadAppData() {
    await loadProjects()
    if (currentUser.role === 'admin') await loadUsers()
    await loadRecentEntries()
    await loadReport()
  }

  onMount(() => {
    if (window.APP_CONFIG?.API_BASE) {
      API_BASE = window.APP_CONFIG.API_BASE
      console.log('[App] API_BASE loaded from config.js:', API_BASE)
    }

    loadSavedThemePreference()
    systemThemeMediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
    if (systemThemeMediaQuery.addEventListener) systemThemeMediaQuery.addEventListener('change', onSystemThemeChange)
    else systemThemeMediaQuery.addListener(onSystemThemeChange)

    ;(async () => {
      setDefaultReportRange()
      const raw = localStorage.getItem('tt_user')
      if (raw) {
        currentUser = JSON.parse(raw)
        form.user_id = String(currentUser.id)
        await loadAppData()
      }
    })()

    return () => {
      if (!systemThemeMediaQuery) return
      if (systemThemeMediaQuery.removeEventListener) systemThemeMediaQuery.removeEventListener('change', onSystemThemeChange)
      else systemThemeMediaQuery.removeListener(onSystemThemeChange)
    }
  })
</script>

<svelte:window on:keydown={closeOpenDialogsByEscape} />

{#if !currentUser}
  <AuthView error={error} loginForm={loginForm} isLoggingIn={isLoggingIn} onLogin={doLogin} />
{:else}
  <main class="panel">
    <SidebarNav
      {currentUser}
      {userMenu}
      {view}
      onChangeView={(next) => (view = next)}
      onOpenPassword={openPasswordDialog}
      onOpenTheme={() => (themeDialogOpen = true)}
      onLogout={logout}
    />

    <section class="content">
      <AlertStack {message} {error} />

      {#if view === 'entry'}
        <EntrySection
          {currentUser}
          {form}
          {users}
          {projects}
          {recentEntries}
          {isSubmittingEntry}
          onSubmitEntry={submitEntry}
          onRefreshRecentEntries={refreshRecentEntries}
          {onEntryProjectKeyDown}
          onOpenJalaliCalendar={openJalaliCalendar}
          {formatJalaliDateWithWeekday}
          {getProjectPillStyle}
          {formatTimeToHHMM}
          {formatMinutesAsHHMM}
          onOpenEntryEditDialog={openEntryEditDialog}
          onDeleteEntry={deleteEntry}
        />
      {/if}

      {#if view === 'users' && currentUser.role === 'admin'}
        <UsersSection
          {users}
          {userForm}
          {userAction}
          {isSavingUser}
          onPickUser={pickUser}
          onDeleteUser={deleteUser}
          onSaveUser={saveUser}
          onList={openUserList}
          onCreate={openUserCreate}
          onEdit={openUserEdit}
          onCloseModal={closeUserModal}
        />
      {/if}

      {#if view === 'projects' && currentUser.role === 'admin'}
        <ProjectsSection
          {projects}
          {projectForm}
          {projectAction}
          {isSavingProject}
          onPickProject={pickProject}
          onDeleteProject={deleteProject}
          onSaveProject={saveProject}
          onList={openProjectList}
          onCreate={openProjectCreate}
          onEdit={openProjectEdit}
        />
      {/if}

      {#if view === 'report'}
        <ReportSection
          {currentUser}
          {users}
          {projects}
          {reportFilter}
          {summary}
          {dailyChart}
          {maxDaily}
          {projectChart}
          {maxProjectMinutes}
          {reportDetails}
          {isLoadingReport}
          onLoadReport={loadReport}
          onOpenJalaliCalendar={openJalaliCalendar}
          {formatMinutesAsHHMM}
          {formatJalaliDateWithWeekday}
          {getJalaliDayLabel}
          {toHours}
          {getProjectPillStyle}
          {formatTimeToHHMM}
          onOpenEntryEditDialog={openEntryEditDialog}
          onDeleteEntry={deleteEntry}
        />
      {/if}
    </section>
  </main>

  <EntryEditDialog
    open={entryEditOpen}
    {currentUser}
    {users}
    {projects}
    {entryEditForm}
    {isSavingEntryEdit}
    onClose={closeEntryEditDialog}
    onSubmit={submitEntryEdit}
    onOpenJalaliCalendar={openJalaliCalendar}
  />

  <PasswordDialog
    open={passwordDialogOpen}
    {passwordForm}
    {isChangingPassword}
    onClose={closePasswordDialog}
    onSubmit={changePassword}
  />

  <ThemeDialog
    open={themeDialogOpen}
    {themePreference}
    onClose={() => (themeDialogOpen = false)}
    onChooseTheme={chooseTheme}
  />

  <JalaliCalendarDialog
    open={calendarVisible}
    {calendarView}
    {jalaliMonthNames}
    {jalaliWeekDays}
    {calendarDays}
    {isSelectedCalendarDay}
    onClose={closeJalaliCalendar}
    onPrevMonth={prevCalendarMonth}
    onNextMonth={nextCalendarMonth}
    onSelectDay={selectCalendarDay}
  />

  <BusyOverlay open={isBusy} />
{/if}

