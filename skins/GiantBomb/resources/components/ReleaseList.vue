<template>
  <main class="listing-main">
    <div v-if="loading" class="listing-loading">
      <div class="loading-spinner"></div>
      <p>Loading new releases...</p>
    </div>

    <div v-else-if="weekGroups.length > 0">
      <div
        v-for="weekGroup in weekGroups"
        :key="weekGroup.label"
        class="listing-group"
      >
        <h2 class="listing-group-label">{{ weekGroup.label }}</h2>
        <div class="listing-grid">
          <div
            v-for="(release, index) in weekGroup.releases"
            :key="index"
            class="listing-card"
          >
            <a :href="release.url" class="listing-card-link">
              <div v-if="release.image" class="listing-card-image">
                <img :src="release.image" :alt="release.title" loading="lazy" />
              </div>
              <div
                v-else
                class="listing-card-image listing-card-image-placeholder"
              >
                <img
                  src="https://www.giantbomb.com/a/uploads/original/11/110673/3026329-gb_default-16_9.png"
                  alt="Giant Bomb Default Image"
                  loading="lazy"
                />
              </div>

              <div class="listing-card-info">
                <h3 class="listing-card-title">{{ release.title }}</h3>
                <div class="listing-card-release-date">
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
                  class="platform-badges"
                >
                  <span
                    v-for="(platform, idx) in release.platforms.slice(0, 3)"
                    :key="idx"
                    class="platform-badge"
                    :title="platform.title"
                  >
                    {{ platform.abbrev }}
                  </span>
                  <span
                    v-if="release.platforms.length > 3"
                    class="platform-badge platform-badge-more"
                    :title="getRemainingPlatformsTitles(release.platforms)"
                  >
                    +{{ release.platforms.length - 3 }}
                  </span>
                </div>
              </div>
            </a>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="listing-empty">
      <p>No releases found for the selected filters.</p>
    </div>
  </main>
</template>

<script>
const { defineComponent, ref, toRefs, onMounted, onUnmounted } = require("vue");
const { getCountryCode, getFlagUrl } = require("../helpers/countryFlags.js");
const { decodeHtmlEntities } = require("../helpers/htmlUtils.js");

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

    // Helper function for remaining platforms tooltip
    const getRemainingPlatformsTitles = (platforms) => {
      return platforms
        .slice(3)
        .map((p) => p.title)
        .join(", ");
    };

    return {
      weekGroups,
      loading,
      getCountryCode,
      getFlagUrl,
      getRemainingPlatformsTitles,
    };
  },
});
</script>

<style>
/* All shared styles are now in listingPage.css */
/* Release-specific styles only below */
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
