<script>
  export let open = false
</script>

{#if open}
  <div class="busy-overlay">
    <div class="busy-spinner"></div>
    <div>در حال پردازش...</div>
  </div>
{/if}
