<template>
  <div class="database-add-prompt">
    <h3 class="prompt-title">Add to the database</h3>
    <p class="prompt-text">
      Are we missing something? Add a new {{ objectType }} to the database by
      using this form.
    </p>
    <a :href="linkUrl" class="add-button"> Create new {{ objectType }} page </a>
  </div>
</template>

<script>
const { toRefs } = require("vue");

/**
 * DatabaseAddPrompt Component
 * Generic component for prompting users to add new items to the database
 */
module.exports = exports = {
  name: "DatabaseAddPrompt",
  props: {
    objectType: {
      type: String,
      required: true,
      default: "item",
    },
    linkUrl: {
      type: String,
      required: true,
      default: "#",
    },
  },
  setup(props) {
    const { objectType, linkUrl } = toRefs(props);

    return {
      objectType,
      linkUrl,
    };
  },
};
</script>

<style>
.database-add-prompt {
  background: #2a2a2a;
  border: 1px solid #444;
  border-radius: 8px;
  padding: 20px;
  margin-top: 20px;
}

.prompt-title {
  margin: 0 0 15px 0;
  font-size: 1.2rem;
  color: #fff;
  border-bottom: 2px solid #444;
  padding-bottom: 10px;
}

.prompt-text {
  margin: 0 0 15px 0;
  color: #ccc;
  font-size: 0.9rem;
  line-height: 1.5;
}

.add-button {
  display: block;
  padding: 12px 20px;
  background: #b05f1c;
  border: none;
  border-radius: 4px;
  color: #fff;
  font-size: 0.95rem;
  font-weight: 600;
  text-align: center;
  text-decoration: none;
  cursor: pointer;
  transition: background 0.2s;
}

.add-button:hover {
  background: #d62839;
}

.add-button:active {
  background: #c21f2c;
}
</style>
