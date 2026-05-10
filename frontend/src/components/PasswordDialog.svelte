<script>
  export let open = false
  export let passwordForm
  export let isChangingPassword = false
  export let onClose = () => {}
  export let onSubmit = () => {}
</script>

{#if open}
  <div class="modal-backdrop" on:click={onClose}>
    <div class="modal theme-modal" on:click|stopPropagation>
      <h4>تغییر رمز عبور</h4>
      <form on:submit|preventDefault={onSubmit}>
        <label>رمز فعلی <input type="password" bind:value={passwordForm.current_password} /></label>
        <label>رمز جدید <input type="password" bind:value={passwordForm.new_password} /></label>
        <label>تکرار رمز جدید <input type="password" bind:value={passwordForm.confirm_password} /></label>
        <div class="modal-actions">
          <button class="primary" type="submit" disabled={isChangingPassword}>💾 ثبت تغییرات</button>
          <button type="button" on:click={onClose}>لغو</button>
        </div>
      </form>
    </div>
  </div>
{/if}
