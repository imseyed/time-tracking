<script>
  export let open = false
  export let currentUser
  export let users = []
  export let projects = []
  export let entryEditForm
  export let isSavingEntryEdit = false
  export let onClose = () => {}
  export let onSubmit = () => {}
  export let onOpenJalaliCalendar = () => {}
</script>

{#if open}
  <div class="modal-backdrop" on:click={onClose}>
    <div class="modal" on:click|stopPropagation>
      <h4>ویرایش رکورد ساعت کاری</h4>
      <form on:submit|preventDefault={onSubmit}>
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
              on:focus={() => onOpenJalaliCalendar('edit')}
              on:click={() => onOpenJalaliCalendar('edit')}
            />
          </label>
          <label>🕒 شروع <input type="time" bind:value={entryEditForm.start_time} /></label>
          <label>🕕 پایان <input type="time" bind:value={entryEditForm.end_time} /></label>
        </div>
        <label>📝 شرح کار <textarea rows="3" bind:value={entryEditForm.description} /></label>
        <div class="modal-actions">
          <button class="primary" type="submit" disabled={isSavingEntryEdit}>💾 ذخیره ویرایش</button>
          <button type="button" on:click={onClose}>لغو</button>
        </div>
      </form>
    </div>
  </div>
{/if}
