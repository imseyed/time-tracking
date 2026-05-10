<script>
  export let users = []
  export let userForm
  export let userAction = 'none'
  export let isSavingUser = false
  export let onPickUser = () => {}
  export let onDeleteUser = () => {}
  export let onSaveUser = () => {}
  export let onList = () => {}
  export let onCreate = () => {}
  export let onEdit = () => {}
  export let onCloseModal = () => {}
</script>

<div class="card">
  <h3>👥 مدیریت کاربران</h3>
  <div class="actions">
    <button on:click={onList}>📋 لیست کاربران</button>
    <button on:click={onCreate}>➕ ایجاد</button>
    <button on:click={onEdit} disabled={!userForm.id}>✏️ ویرایش</button>
    <button on:click={() => userForm.id && onDeleteUser(Number(userForm.id))} disabled={!userForm.id}>🗑️ حذف</button>
  </div>

  <div class="table-wrap">
    <table>
      <thead><tr><th>نام</th><th>نام کاربری</th><th>نقش</th></tr></thead>
      <tbody>
        {#each users as u}
          <tr class="clickable-row" class:selected={String(u.id)===userForm.id} on:click={() => onPickUser(u)}>
            <td>{u.full_name}</td><td>{u.username}</td><td>{u.role}</td>
          </tr>
        {/each}
      </tbody>
    </table>
  </div>

  {#if userAction === 'create' || userAction === 'edit'}
    <div class="modal-backdrop" on:click={onCloseModal}>
      <div class="modal" on:click|stopPropagation>
        <h4>{userAction === 'create' ? 'ایجاد کاربر' : 'ویرایش کاربر'}</h4>
        <form on:submit|preventDefault={onSaveUser}>
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
            <button type="button" on:click={onCloseModal}>لغو</button>
          </div>
        </form>
      </div>
    </div>
  {/if}
</div>
