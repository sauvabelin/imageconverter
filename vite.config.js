import { createAppConfig } from '@nextcloud/vite-config'

// @nextcloud/vite-config already includes @vitejs/plugin-vue internally;
// adding it again here causes double-parse and breaks .vue SFC builds.
export default createAppConfig({
	main: 'src/main.ts',
})
