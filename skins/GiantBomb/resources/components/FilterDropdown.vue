<template>
  <div class="filter-group">
    <label v-if="label" :for="id" class="filter-label">{{ label }}</label>
    <select
      :id="id"
      :value="modelValue"
      @change="$emit('update:modelValue', $event.target.value)"
      class="filter-select"
    >
      <option v-if="placeholder" value="">{{ placeholder }}</option>
      <option
        v-for="option in options"
        :key="getOptionValue(option)"
        :value="getOptionValue(option)"
      >
        {{ getOptionLabel(option) }}
      </option>
    </select>
  </div>
</template>

<script>
/**
 * FilterDropdown Component
 * Generic reusable dropdown filter component
 */
const { defineComponent } = require("vue");

const component = defineComponent({
  name: "FilterDropdown",
  props: {
    id: {
      type: String,
      required: true,
    },
    label: {
      type: String,
      default: "",
    },
    modelValue: {
      type: [String, Number],
      default: "",
    },
    options: {
      type: Array,
      required: true,
    },
    placeholder: {
      type: String,
      default: "",
    },
    // For object arrays: specify which property to use as value
    valueKey: {
      type: String,
      default: null,
    },
    // For object arrays: specify which property to use as label
    labelKey: {
      type: String,
      default: null,
    },
  },
  emits: ["update:modelValue"],
  setup(props) {
    const getOptionValue = (option) => {
      // Try parsing the option as JSON if it's a string
      try {
        option = JSON.parse(option);
      } catch (e) {
        // Treat as string if parsing fails
      }
      if (typeof option === "object" && props.valueKey) {
        return option[props.valueKey];
      }
      return option;
    };

    const getOptionLabel = (option) => {
      // Try parsing the option as JSON if it's a string
      try {
        option = JSON.parse(option);
      } catch (e) {
        // Treat as string if parsing fails
      }
      if (typeof option === "object" && props.labelKey) {
        return option[props.labelKey];
      }
      return option;
    };

    return {
      getOptionValue,
      getOptionLabel,
    };
  },
});

module.exports = exports = component;
exports.default = component;
</script>

<style>
.filter-group {
  margin-bottom: 20px;
}

.filter-label {
  display: block;
  margin-bottom: 8px;
  color: #ccc;
  font-size: 0.9rem;
  font-weight: 600;
}

.filter-select {
  width: 100%;
  padding: 10px;
  background: #1a1a1a;
  border: 1px solid #444;
  border-radius: 4px;
  color: #fff;
  font-size: 0.95rem;
  cursor: pointer;
  transition: border-color 0.2s;
}

.filter-select:hover {
  border-color: #666;
}

.filter-select:focus {
  outline: none;
  border-color: #b05f1c;
}
</style>
