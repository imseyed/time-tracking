<script>
  export let currentUser
  export let users = []
  export let projects = []
  export let reportFilter
  export let summary = { entries_count: 0, total_hours: 0 }
  export let dailyChart = []
  export let maxDaily = 1
  export let projectChart = []
  export let maxProjectMinutes = 1
  export let reportDetails = []
  export let isLoadingReport = false
  export let onLoadReport = () => {}
  export let onOpenJalaliCalendar = () => {}
  export let formatMinutesAsHHMM = (v) => v
  export let formatJalaliDateWithWeekday = (v) => v
  export let getJalaliDayLabel = (v) => v
  export let toHours = (v) => v
  export let getProjectPillStyle = () => ''
  export let formatTimeToHHMM = (v) => v
  export let onOpenEntryEditDialog = () => {}
  export let onDeleteEntry = () => {}
</script>

<div class="card">
  <h3>📊 گزارش‌گیری</h3>
  <form on:submit|preventDefault={onLoadReport}>
    <div class="grid">
      {#if currentUser.role === 'admin'}
        <label>👤 کاربر
          <select bind:value={reportFilter.user_id} on:change={onLoadReport}>
            <option value="">همه</option>
            {#each users as u}<option value={u.id}>{u.full_name}</option>{/each}
          </select>
        </label>
      {/if}
      <label>📁 پروژه
        <select bind:value={reportFilter.project_id} on:change={onLoadReport}><option value="">همه</option>{#each projects as p}<option value={p.id}>{p.name}</option>{/each}</select>
      </label>
      <label>📅 از تاریخ شمسی
        <input
          bind:value={reportFilter.from_date_jalali}
          placeholder="1405/01/01"
          on:focus={() => onOpenJalaliCalendar('from')}
          on:click={() => onOpenJalaliCalendar('from')}
          on:blur={onLoadReport}
        />
      </label>
      <label>📅 تا تاریخ شمسی
        <input
          bind:value={reportFilter.to_date_jalali}
          placeholder="1405/01/30"
          on:focus={() => onOpenJalaliCalendar('to')}
          on:click={() => onOpenJalaliCalendar('to')}
          on:blur={onLoadReport}
        />
      </label>
    </div>
    <button class="primary" type="submit" disabled={isLoadingReport}>🔎 اعمال فیلتر</button>
  </form>

  <div class="summary"><span>تعداد: {summary.entries_count}</span><span>کل ساعت: {summary.total_hours}</span></div>

  <h4>نمودار عمودی ساعت روزانه</h4>
  <div class="vchart">
    {#each dailyChart as d}
      <div class="vbar-col" title={`${d.work_date_j} - ${formatMinutesAsHHMM(d.total_minutes)}`}>
        <small class="vbar-value">{formatMinutesAsHHMM(d.total_minutes)}</small>
        <div class="vbar" style={`height:${Math.max(8, (Number(d.total_minutes) / maxDaily) * 180)}px`}></div>
        <small>{getJalaliDayLabel(d.work_date_j)}</small>
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
      <thead><tr><th>تاریخ</th><th>کاربر</th><th>پروژه</th><th>شروع</th><th>پایان</th><th>ساعت:دقیقه</th><th>شرح</th><th>عملیات</th></tr></thead>
      <tbody>
        {#if reportDetails.length===0}
          <tr><td colspan="8">داده‌ای وجود ندارد.</td></tr>
        {:else}
          {#each reportDetails as row}
            <tr>
              <td>{formatJalaliDateWithWeekday(row.work_date_j)}</td>
              <td>{row.user_name}</td>
              <td><span class="project-pill" style={getProjectPillStyle(row)}>{row.project_name}</span></td>
              <td>{formatTimeToHHMM(row.start_time)}</td>
              <td>{formatTimeToHHMM(row.end_time)}</td>
              <td>{formatMinutesAsHHMM(row.duration_minutes)}</td>
              <td>{row.description}</td>
              <td>
                <div class="row-actions">
                  <button on:click={() => onOpenEntryEditDialog(row)}>✏️ ویرایش</button>
                  <button class="danger" on:click={() => onDeleteEntry(row)}>🗑️ حذف</button>
                </div>
              </td>
            </tr>
          {/each}
        {/if}
      </tbody>
    </table>
  </div>
</div>
