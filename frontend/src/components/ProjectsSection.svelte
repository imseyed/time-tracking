<script>
  export let projects = []
  export let projectForm
  export let projectAction = 'list'
  export let isSavingProject = false
  export let onPickProject = () => {}
  export let onDeleteProject = () => {}
  export let onSaveProject = () => {}
  export let onList = () => {}
  export let onCreate = () => {}
  export let onEdit = () => {}
</script>

<div class="card">
  <h3>📁 مدیریت پروژه‌ها</h3>
  <div class="actions">
    <button on:click={onList}>📋 لیست پروژه‌ها</button>
    <button on:click={onCreate}>➕ ایجاد</button>
    <button on:click={onEdit} disabled={!projectForm.id}>✏️ ویرایش</button>
    <button on:click={() => projectForm.id && onDeleteProject(Number(projectForm.id))} disabled={!projectForm.id}>🗑️ حذف</button>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>نام پروژه</th><th>رنگ</th></tr></thead>
      <tbody>
        {#each projects as p}
          <tr class="clickable-row" class:selected={String(p.id)===projectForm.id} on:click={() => onPickProject(p)}>
            <td><span class="project-pill" style={`background:${p.color}1a;border-color:${p.color};color:${p.color};`}>{p.name}</span></td>
            <td><span class="dot" style={`background:${p.color}`}></span> {p.color}</td>
          </tr>
        {/each}
      </tbody>
    </table>
  </div>

  {#if projectAction !== 'list'}
    <form on:submit|preventDefault={onSaveProject}>
      <div class="grid">
        <label>نام پروژه <input bind:value={projectForm.name} /></label>
        <label>رنگ <input type="color" bind:value={projectForm.color} /></label>
      </div>
      <button class="primary" type="submit" disabled={isSavingProject}>💾 ذخیره پروژه</button>
    </form>
  {/if}
</div>
