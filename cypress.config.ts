import { defineConfig } from "cypress";
import dotenvPlugin from "cypress-dotenv";

export default defineConfig({
  e2e: {
    setupNodeEvents(on, config) {
      const updatedConfig = dotenvPlugin(config, null, true);
      return updatedConfig;
    },
    baseUrl: "http://localhost:8080/index.php",
  },
  defaultCommandTimeout: 10000,
});
