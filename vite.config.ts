import { defineConfig } from '@ownclouders/extension-sdk'

export default defineConfig({
    server: {
        port: 5566
    },
    build: {
        rollupOptions: {
            output: {
                dir: "./js/web/",
                entryFileNames: `[name].js`
            }
        }
    }
})
