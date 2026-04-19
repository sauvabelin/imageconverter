import { createAppConfig } from '@nextcloud/vite-config'
import vue from '@vitejs/plugin-vue'

export default createAppConfig({
	main: 'src/main.ts',
}, {
	config: {
		plugins: [vue()],
	},
})