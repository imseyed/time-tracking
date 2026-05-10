<script>
  export let open = false
  export let themePreference = 'system'
  export let onClose = () => {}
  export let onChooseTheme = () => {}
</script>

{#if open}
  <div class="modal-backdrop" on:click={onClose}>
    <div class="modal theme-modal" on:click|stopPropagation>
      <h4>تنظیم تم</h4>
      <div class="theme-option-grid">
        <button
          type="button"
          class="theme-choice"
          class:active={themePreference === 'system'}
          on:click={() => onChooseTheme('system')}
        >خودکار (سیستم)</button>
        <button
          type="button"
          class="theme-choice"
          class:active={themePreference === 'light'}
          on:click={() => onChooseTheme('light')}
        >روشن</button>
        <button
          type="button"
          class="theme-choice"
          class:active={themePreference === 'dark'}
          on:click={() => onChooseTheme('dark')}
        >تیره</button>
      </div>
    </div>
  </div>
{/if}
