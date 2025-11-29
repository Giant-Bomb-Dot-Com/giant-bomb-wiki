<template>
  <main class="releases-main">
    <div v-if="loading" class="releases-loading">
      <div class="loading-spinner"></div>
      <p>Loading new releases...</p>
    </div>

    <div v-else-if="weekGroups.length > 0">
      <div
        v-for="weekGroup in weekGroups"
        :key="weekGroup.label"
        class="week-group"
      >
        <h2 class="week-label">{{ weekGroup.label }}</h2>
        <div class="releases-grid">
          <div
            v-for="(release, index) in weekGroup.releases"
            :key="index"
            class="release-card"
          >
            <a :href="release.url" class="release-card-link">
              <div v-if="release.image" class="release-image">
                <img :src="release.image" :alt="release.title" loading="lazy" />
              </div>
              <div v-else class="release-image release-image-placeholder">
                <img
                  src="https://www.giantbomb.com/a/uploads/original/11/110673/3026329-gb_default-16_9.png"
                  alt="Giant Bomb Default Image"
                  loading="lazy"
                />
              </div>

              <div class="release-info">
                <h3 class="release-title">{{ release.title }}</h3>
                <div class="release-date">
                  <span v-if="release.releaseDateFormatted">{{
                    release.releaseDateFormatted
                  }}</span>
                  <img
                    v-if="release.region && getCountryCode(release.region)"
                    :src="getFlagUrl(getCountryCode(release.region), 'w20')"
                    :alt="release.region"
                    :title="release.region"
                    class="region-flag"
                    loading="lazy"
                  />
                  <span v-else-if="release.region" class="region-text">
                    ({{ release.region }})</span
                  >
                </div>

                <div
                  v-if="release.platforms && release.platforms.length > 0"
                  class="release-platforms"
                >
                  <span
                    v-for="(platform, idx) in release.platforms"
                    :key="idx"
                    class="platform-badge"
                    :title="platform.title"
                  >
                    {{ platform.abbrev }}
                  </span>
                </div>
              </div>
            </a>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="no-releases">
      <p>No releases found for the selected filters.</p>
    </div>
  </main>
</template>

<script>
const { defineComponent, ref, toRefs, onMounted, onUnmounted } = require("vue");
const { getCountryCode, getFlagUrl } = require("../helpers/countryFlags.js");

/**
 * ReleaseList Component
 * Displays releases and handles async filtering
 */
module.exports = exports = defineComponent({
  name: "ReleaseList",
  props: {
    initialData: {
      type: String,
      required: true,
    },
  },
  setup(props) {
    const { initialData } = toRefs(props);
    const weekGroups = ref([]);
    const loading = ref(false);

    // Helper function to decode HTML entities
    const { decodeHtmlEntities } = require("../helpers/htmlUtils.js");

    const fetchReleases = async (region = "", platform = "") => {
      loading.value = true;

      try {
        // Build API URL
        const params = new URLSearchParams();
        params.set("action", "get-releases");
        if (region) params.set("region", region);
        if (platform) params.set("platform", platform);

        const url = `${window.location.pathname}?${params.toString()}`;

        const response = await fetch(url, {
          method: "GET",
          credentials: "same-origin",
          headers: {
            Accept: "application/json",
          },
        });

        if (!response.ok) {
          const text = await response.text();
          console.error("Response body:", text);
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
          weekGroups.value = data.weekGroups || [];
        } else {
          console.error("API returned error:", data);
          weekGroups.value = [];
        }
      } catch (error) {
        console.error("Failed to fetch releases:", error);
        // Keep existing data on error
      } finally {
        loading.value = false;
      }
    };

    const handleFilterChange = (event) => {
      const { region, platform } = event.detail;
      fetchReleases(region, platform);
    };

    onMounted(() => {
      // Parse initial server-rendered data
      try {
        const decoded = decodeHtmlEntities(initialData.value);
        weekGroups.value = JSON.parse(decoded);
      } catch (e) {
        console.error("Failed to parse initial data:", e);
        weekGroups.value = [];
      }

      // Listen for filter changes
      window.addEventListener("releases-filter-changed", handleFilterChange);
    });

    onUnmounted(() => {
      window.removeEventListener("releases-filter-changed", handleFilterChange);
    });

    return {
      weekGroups,
      loading,
      getCountryCode,
      getFlagUrl,
    };
  },
});
</script>

<style>
.releases-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  color: #999;
}

.loading-spinner {
  width: 50px;
  height: 50px;
  border: 4px solid #333;
  border-top-color: #e63946;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 20px;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

.releases-loading p {
  font-size: 1.1rem;
  margin: 0;
}

.region-flag {
  margin-left: 6px;
  height: 14px;
  width: auto;
  vertical-align: middle;
  cursor: help;
  border-radius: 2px;
}

.region-text {
  color: #999;
}
</style>
