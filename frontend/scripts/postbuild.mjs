import { copyFileSync, cpSync, existsSync, rmSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const __filename = fileURLToPath(import.meta.url)
const __dirname = dirname(__filename)

const frontendRoot = resolve(__dirname, '..')
const distDir = resolve(frontendRoot, 'dist')
const backendDir = resolve(frontendRoot, '..', 'backend')
const exampleConfigSrc = resolve(frontendRoot, 'public', 'config.example.js')

if (!existsSync(distDir)) {
  throw new Error(`dist folder not found: ${distDir}`)
}

if (existsSync(backendDir)) {
  cpSync(backendDir, resolve(distDir, 'backend'), { recursive: true, force: true })
}

if (existsSync(resolve(distDir, 'config.js'))) {
  rmSync(resolve(distDir, 'config.js'), { force: true })
}

if (existsSync(resolve(distDir, 'config.example.js'))) {
  rmSync(resolve(distDir, 'config.example.js'), { force: true })
}

if (existsSync(exampleConfigSrc)) {
  copyFileSync(exampleConfigSrc, resolve(distDir, 'config.js.example'))
}
