<script>
  export let currentUser
  export let form
  export let users = []
  export let projects = []
  export let recentEntries = []
  export let isSubmittingEntry = false
  export let onSubmitEntry = () => {}
  export let onRefreshRecentEntries = () => {}
  export let onEntryProjectKeyDown = () => {}
  export let onOpenJalaliCalendar = () => {}
  export let formatJalaliDateWithWeekday = (v) => v
  export let getProjectPillStyle = () => ''
  export let formatTimeToHHMM = (v) => v
  export let formatMinutesAsHHMM = (v) => v
  export let onOpenEntryEditDialog = () => {}
  export let onDeleteEntry = () => {}
</script>

<div class="card">
  <h3>⏱️ ثبت ساعت کاری</h3>
  <form on:submit|preventDefault={onSubmitEntry}>
    <div class="grid">
      {#if currentUser.role === 'admin'}
        <label>👤 کاربر
          <select bind:value={form.user_id} on:change={onRefreshRecentEntries}>
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
          on:focus={() => onOpenJalaliCalendar('entry')}
          on:click={() => onOpenJalaliCalendar('entry')}
        />
      </label>
      <label>🕒 شروع <input type="time" step="60" bind:value={form.start_time} /></label>
      <label>🕕 پایان <input type="time" step="60" bind:value={form.end_time} /></label>
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
          <th>پروژه</th><th>شروع</th><th>پایان</th><th>ساعت:دقیقه</th><th>شرح</th><th>عملیات</th>
        </tr>
      </thead>
      <tbody>
        {#if recentEntries.length === 0}
          <tr><td colspan={currentUser.role === 'admin' ? 8 : 7}>رکوردی ثبت نشده است.</td></tr>
        {:else}
          {#each recentEntries as row}
            <tr>
              <td>{formatJalaliDateWithWeekday(row.work_date_j)}</td>
              {#if currentUser.role === 'admin'}<td>{row.user_name}</td>{/if}
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
