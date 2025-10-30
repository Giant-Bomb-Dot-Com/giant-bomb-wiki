<template>
  <div class="game-tags-interactive">
    <div class="game-tags">
      <a
        v-for="(tag, index) in tags"
        :key="index"
        :href="getTagUrl(tag)"
        :class="[
          'game-tag',
          `game-tag--${tagType}`,
          { 'game-tag--active': activeTag === tag },
        ]"
        @click.prevent="handleTagClick(tag)"
      >
        {{ tag }}
      </a>
    </div>
  </div>
</template>

<script>
const { ref, toRefs } = require("vue");

module.exports = exports = {
  name: "GameTags",
  props: {
    tags: {
      type: String,
      required: true,
    },
    tagType: {
      type: String,
      default: "default",
    },
    namespace: {
      type: String,
      default: "",
    },
  },
  setup(props) {
    const { tags, tagType, namespace } = toRefs(props);
    const activeTag = ref(null);

    // Parse tags from comma-separated string
    const tagList = tags.value
      ? tags.value.split(",").map((t) => t.trim())
      : [];

    const getTagUrl = (tag) => {
      // If namespace is provided, create a proper wiki link
      if (namespace.value) {
        const encodedTag = tag.replace(/ /g, "_");
        return `/index.php/${namespace.value}/${encodedTag}`;
      }
      // Otherwise, search for the tag
      return `/index.php/Special:Search?search=${encodeURIComponent(tag)}`;
    };

    const handleTagClick = (tag) => {
      activeTag.value = tag;
      // Navigate to the tag URL
      window.location.href = getTagUrl(tag);
    };

    return {
      tags: tagList,
      tagType,
      namespace,
      activeTag,
      getTagUrl,
      handleTagClick,
    };
  },
};
</script>

<style>
.game-tags-interactive {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.game-tag {
  cursor: pointer;
  text-decoration: none;
  transition: all 0.2s ease;
}

.game-tag:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.game-tag--active {
  box-shadow: 0 0 0 2px #4caf50;
  transform: scale(1.05);
}
</style>
