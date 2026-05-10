<script>
  export let open = false
  export let calendarView
  export let jalaliMonthNames = []
  export let jalaliWeekDays = []
  export let calendarDays = []
  export let isSelectedCalendarDay = () => false
  export let onClose = () => {}
  export let onPrevMonth = () => {}
  export let onNextMonth = () => {}
  export let onSelectDay = () => {}
</script>

{#if open}
  <div class="calendar-backdrop" on:click={onClose}>
    <div class="calendar-popup" on:click|stopPropagation>
      <div class="calendar-head">
        <button type="button" on:click={onNextMonth}>‹</button>
        <strong>{jalaliMonthNames[calendarView.jm - 1]} {calendarView.jy}</strong>
        <button type="button" on:click={onPrevMonth}>›</button>
      </div>
      <div class="calendar-grid weekdays">
        {#each jalaliWeekDays as wd}<span>{wd}</span>{/each}
      </div>
      <div class="calendar-grid days">
        {#each calendarDays as d}
          <button
            type="button"
            class:empty={!d}
            class:selected={isSelectedCalendarDay(d)}
            disabled={!d}
            on:click={() => onSelectDay(d)}
          >
            {d ?? ''}
          </button>
        {/each}
      </div>
    </div>
  </div>
{/if}
