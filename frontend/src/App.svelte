<script>
  import { onMount } from 'svelte'

  const API_BASE = 'http://localhost:8080/api.php'

  let tab = 'entry'
  let users = []
  let projects = []

  let form = {
    user_id: '',
    project_id: '',
    work_date: new Date().toISOString().slice(0, 10),
    start_time: '',
    end_time: '',
    description: ''
  }

  let reportFilter = {
    user_id: '',
    project_id: '',
    from_date: '',
    to_date: ''
  }

  let saving = false
  let loadingReport = false
  let message = ''
  let error = ''

  let reportDetails = []
  let chartData = []
  let summary = { total_minutes: 0, total_hours: 0, entries_count: 0 }

  const toHours = (minutes) => (minutes / 60).toFixed(2)
  $: maxChart = Math.max(1, ...chartData.map((d) => Number(d.total_minutes)))

  async function loadInitialData() {
    try {
      const [usersRes, projectsRes] = await Promise.all([
        fetch(`${API_BASE}?action=users`),
        fetch(`${API_BASE}?action=projects`)
      ])
      const usersJson = await usersRes.json()
      const projectsJson = await projectsRes.json()

      users = usersJson.data ?? []
      projects = projectsJson.data ?? []

      if (users.length && !form.user_id) form.user_id = String(users[0].id)
      if (projects.length && !form.project_id) form.project_id = String(projects[0].id)
    } catch (e) {
      error = 'دریافت اطلاعات اولیه انجام نشد.'
    }
  }

  async function submitEntry() {
    message = ''
    error = ''
    saving = true

    try {
      const res = await fetch(`${API_BASE}?action=entry-create`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          ...form,
          user_id: Number(form.user_id),
          project_id: Number(form.project_id)
        })
      })
      const json = await res.json()
      if (!res.ok) throw new Error(json.error || 'ثبت انجام نشد.')

      message = json.message || 'ثبت شد.'
      form.start_time = ''
      form.end_time = ''
      form.description = ''
    } catch (e) {
      error = e.message
    } finally {
      saving = false
    }
  }

  async function loadReport() {
    message = ''
    error = ''
    loadingReport = true

    try {
      const qs = new URLSearchParams({ action: 'report' })
      if (reportFilter.user_id) qs.set('user_id', reportFilter.user_id)
      if (reportFilter.project_id) qs.set('project_id', reportFilter.project_id)
      if (reportFilter.from_date) qs.set('from_date', reportFilter.from_date)
      if (reportFilter.to_date) qs.set('to_date', reportFilter.to_date)

      const res = await fetch(`${API_BASE}?${qs.toString()}`)
      const json = await res.json()
      if (!res.ok) throw new Error(json.error || 'گزارش دریافت نشد.')

      reportDetails = json.details ?? []
      chartData = json.chart ?? []
      summary = json.summary ?? summary
    } catch (e) {
      error = e.message
    } finally {
      loadingReport = false
    }
  }

  onMount(async () => {
    await loadInitialData()
    await loadReport()
  })
</script>

<main class="app">
  <header class="topbar">
    <h1>سامانه ثبت ساعت کاری</h1>
    <p>طراحی مشابه Clockify با تم نارنجی</p>
  </header>

  <nav class="tabs">
    <button class:active={tab === 'entry'} on:click={() => (tab = 'entry')}>ثبت ساعت</button>
    <button class:active={tab === 'report'} on:click={() => (tab = 'report')}>گزارش‌گیری</button>
  </nav>

  {#if message}<div class="alert success">{message}</div>{/if}
  {#if error}<div class="alert error">{error}</div>{/if}

  {#if tab === 'entry'}
    <section class="card">
      <h2>ثبت ساعت کاری</h2>
      <div class="grid">
        <label>فرد
          <select bind:value={form.user_id}>
            {#each users as u}<option value={u.id}>{u.full_name}</option>{/each}
          </select>
        </label>
        <label>پروژه
          <select bind:value={form.project_id}>
            {#each projects as p}<option value={p.id}>{p.name}</option>{/each}
          </select>
        </label>
        <label>تاریخ
          <input type="date" bind:value={form.work_date} />
        </label>
        <label>شروع
          <input type="time" bind:value={form.start_time} />
        </label>
        <label>پایان
          <input type="time" bind:value={form.end_time} />
        </label>
      </div>
      <label>شرح فعالیت
        <textarea rows="4" bind:value={form.description} placeholder="چه کاری انجام شد؟"></textarea>
      </label>

      <button class="primary" on:click={submitEntry} disabled={saving}>
        {saving ? 'در حال ثبت...' : 'ثبت ساعت کاری'}
      </button>
    </section>
  {:else}
    <section class="card">
      <h2>گزارش‌گیری</h2>
      <div class="grid">
        <label>فرد
          <select bind:value={reportFilter.user_id}>
            <option value="">همه</option>
            {#each users as u}<option value={u.id}>{u.full_name}</option>{/each}
          </select>
        </label>
        <label>پروژه
          <select bind:value={reportFilter.project_id}>
            <option value="">همه</option>
            {#each projects as p}<option value={p.id}>{p.name}</option>{/each}
          </select>
        </label>
        <label>از تاریخ
          <input type="date" bind:value={reportFilter.from_date} />
        </label>
        <label>تا تاریخ
          <input type="date" bind:value={reportFilter.to_date} />
        </label>
      </div>

      <button class="primary" on:click={loadReport} disabled={loadingReport}>
        {loadingReport ? 'در حال بارگذاری...' : 'اعمال فیلتر'}
      </button>

      <div class="summary">
        <div><strong>کل رکوردها:</strong> {summary.entries_count}</div>
        <div><strong>کل ساعت:</strong> {summary.total_hours}</div>
      </div>

      <h3>نمودار ساعات به تفکیک پروژه</h3>
      <div class="chart">
        {#if chartData.length === 0}
          <p>داده‌ای برای نمایش وجود ندارد.</p>
        {:else}
          {#each chartData as item}
            <div class="bar-row">
              <div class="bar-label">{item.project_name} ({toHours(Number(item.total_minutes))}h)</div>
              <div class="bar-track">
                <div class="bar-fill" style={`width:${(Number(item.total_minutes) / maxChart) * 100}%; background:${item.project_color}`}></div>
              </div>
            </div>
          {/each}
        {/if}
      </div>

      <h3>جزئیات</h3>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>تاریخ</th>
              <th>فرد</th>
              <th>پروژه</th>
              <th>شروع</th>
              <th>پایان</th>
              <th>مدت (دقیقه)</th>
              <th>شرح</th>
            </tr>
          </thead>
          <tbody>
            {#if reportDetails.length === 0}
              <tr><td colspan="7">رکوردی یافت نشد.</td></tr>
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
    </section>
  {/if}
</main>

<style>
  :global(body) {
    margin: 0;
    font-family: Vazirmatn, Tahoma, sans-serif;
    background: #fff8f5;
    color: #2f2f2f;
  }
  .app {
    max-width: 980px;
    margin: 0 auto;
    padding: 24px;
  }
  .topbar {
    margin-bottom: 16px;
  }
  .topbar h1 {
    margin: 0;
    color: #fc572c;
  }
  .tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
  }
  button {
    border: 1px solid #ffb19c;
    background: white;
    color: #fc572c;
    border-radius: 10px;
    padding: 10px 14px;
    cursor: pointer;
  }
  button.active,
  .primary {
    background: #fc572c;
    color: white;
    border-color: #fc572c;
  }
  .card {
    background: white;
    border: 1px solid #ffd3c6;
    border-radius: 16px;
    padding: 18px;
    box-shadow: 0 10px 30px rgba(252, 87, 44, 0.08);
  }
  .grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 12px;
    margin-bottom: 12px;
  }
  label {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 0.95rem;
  }
  input,
  select,
  textarea {
    border: 1px solid #ffc6b5;
    border-radius: 10px;
    padding: 10px;
    font: inherit;
    background: #fff;
  }
  .alert {
    padding: 10px 14px;
    border-radius: 10px;
    margin-bottom: 10px;
  }
  .success { background: #ffe5dc; color: #a13012; }
  .error { background: #ffe7e7; color: #9b1f1f; }
  .summary {
    margin: 14px 0;
    display: flex;
    gap: 20px;
  }
  .chart {
    margin-bottom: 20px;
  }
  .bar-row {
    margin-bottom: 10px;
  }
  .bar-label {
    margin-bottom: 4px;
    font-size: 0.9rem;
  }
  .bar-track {
    width: 100%;
    background: #ffe8df;
    border-radius: 8px;
    overflow: hidden;
    height: 16px;
  }
  .bar-fill {
    height: 100%;
    border-radius: 8px;
  }
  .table-wrap {
    overflow-x: auto;
  }
  table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
  }
  th,
  td {
    border-bottom: 1px solid #ffe0d6;
    text-align: right;
    padding: 8px;
    font-size: 0.9rem;
  }
</style>
