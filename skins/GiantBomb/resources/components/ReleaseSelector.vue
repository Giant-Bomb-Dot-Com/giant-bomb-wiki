<template>
  <div class="release-selector">
    <div class="release-selector__label" v-if="releasesData.length > 0">
      Select specific release
    </div>
    <select
      v-model="selectedReleaseIndex"
      class="release-selector__dropdown"
      v-if="releasesData.length > 0"
    >
      <option
        v-for="(release, index) in releasesData"
        :key="index"
        :value="index"
      >
        {{ release.displayName }}
      </option>
    </select>

    <div class="game-details" v-if="selectedRelease">
      <div class="game-detail-item">
        <div class="game-detail-label">Release date</div>
        <div class="game-detail-value">{{ selectedRelease.releaseDate }}</div>
      </div>
      <div class="game-detail-item">
        <div class="game-detail-label">Rating</div>
        <div class="game-detail-value">{{ selectedRelease.rating }}</div>
      </div>
      <div class="game-detail-item">
        <div class="game-detail-label">Resolutions</div>
        <div class="game-detail-value">{{ selectedRelease.resolutions }}</div>
      </div>
      <div class="game-detail-item">
        <div class="game-detail-label">Surround sound</div>
        <div class="game-detail-value">{{ selectedRelease.soundSystems }}</div>
      </div>
      <div class="game-detail-item">
        <div class="game-detail-label">Widescreen</div>
        <div class="game-detail-value">
          {{ selectedRelease.widescreenSupport }}
        </div>
      </div>
    </div>
  </div>
</template>

<script>
/**
 * ReleaseSelector Component
 * Dropdown selector for viewing different game release details
 */
module.exports = exports = {
  name: "ReleaseSelector",
  props: {
    releases: {
      type: String,
      required: true,
    },
  },
  data() {
    return {
      releasesData: [],
      selectedReleaseIndex: 0,
    };
  },
  computed: {
    selectedRelease() {
      return this.releasesData[this.selectedReleaseIndex] || null;
    },
  },
  mounted() {
    try {
      this.releasesData = JSON.parse(this.releases);
    } catch (e) {
      console.error("Failed to parse releases data:", e);
      this.releasesData = [];
    }
  },
};
</script>
