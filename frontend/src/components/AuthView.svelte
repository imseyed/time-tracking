<script>
  export let error = ''
  export let loginForm
  export let isLoggingIn = false
  export let onLogin = () => {}
</script>

<main class="auth-page">
  <section class="card auth-card">
    <h1>⏱️ ورود به سامانه</h1>
    {#if error}<div class="alert error">{error}</div>{/if}
    <form on:submit|preventDefault={onLogin}>
      <label>👤 نام کاربری <input bind:value={loginForm.username} /></label>
      <label>🔐 رمز عبور <input type="password" bind:value={loginForm.password} /></label>
      <button class="primary" type="submit" disabled={isLoggingIn}>🔓 ورود</button>
    </form>
  </section>
</main>
