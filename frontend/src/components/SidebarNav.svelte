<script>
  export let currentUser
  export let userMenu = []
  export let view = 'entry'
  export let onChangeView = () => {}
  export let onOpenPassword = () => {}
  export let onOpenTheme = () => {}
  export let onLogout = () => {}
</script>

<aside class="sidebar">
  <h2><img class="panel-icon" src="./favicon.svg" alt="ICO"> Time Panel</h2>
  <small>{currentUser.full_name}</small>
  <nav>
    {#each userMenu as item}
      <button class:active={view === item.key} on:click={() => onChangeView(item.key)}>{item.icon} {item.label}</button>
    {/each}
  </nav>
  <button on:click={onOpenPassword}>🔐 تغییر رمز عبور</button>
  <button class="theme-btn" on:click={onOpenTheme}>🎨 تنظیم تم</button>
  <button class="logout" on:click={onLogout}>🚪 خروج</button>
</aside>
