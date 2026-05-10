<script>
  export let message = ''
  export let error = ''
</script>

{#if message}<div class="alert success">{message}</div>{/if}
{#if error}<div class="alert error">{error}</div>{/if}
