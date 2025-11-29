const { mount } = require("@vue/test-utils");
const FilterContainer = require("../FilterContainer.vue");

describe("FilterContainer", () => {
  let wrapper;

  afterEach(() => {
    if (wrapper) {
      wrapper.unmount();
    }
  });

  describe("Component Rendering", () => {
    it("renders with default props", () => {
      wrapper = mount(FilterContainer);

      expect(wrapper.find(".filter-container").exists()).toBe(true);
      expect(wrapper.find(".filter-title").exists()).toBe(true);
      expect(wrapper.find(".filter-title").text()).toBe("Filter");
    });

    it("renders with custom title", () => {
      wrapper = mount(FilterContainer, {
        props: {
          title: "Custom Filter Title",
        },
      });

      expect(wrapper.find(".filter-title").text()).toBe("Custom Filter Title");
    });

    it("does not render title when title prop is empty", () => {
      wrapper = mount(FilterContainer, {
        props: {
          title: "",
        },
      });

      expect(wrapper.find(".filter-title").exists()).toBe(false);
    });

    it("renders slot content", () => {
      wrapper = mount(FilterContainer, {
        slots: {
          default: '<div class="test-content">Test Content</div>',
        },
      });

      expect(wrapper.find(".test-content").exists()).toBe(true);
      expect(wrapper.find(".test-content").text()).toBe("Test Content");
    });

    it("renders multiple child elements in slot", () => {
      wrapper = mount(FilterContainer, {
        slots: {
          default: `
            <div class="child-1">Child 1</div>
            <div class="child-2">Child 2</div>
            <div class="child-3">Child 3</div>
          `,
        },
      });

      expect(wrapper.find(".child-1").exists()).toBe(true);
      expect(wrapper.find(".child-2").exists()).toBe(true);
      expect(wrapper.find(".child-3").exists()).toBe(true);
    });
  });

  describe("Clear Button", () => {
    it("does not show clear button by default", () => {
      wrapper = mount(FilterContainer);

      expect(wrapper.find(".clear-filters-btn").exists()).toBe(false);
    });

    it("shows clear button when showClearButton is true", () => {
      wrapper = mount(FilterContainer, {
        props: {
          showClearButton: true,
        },
      });

      expect(wrapper.find(".clear-filters-btn").exists()).toBe(true);
    });

    it("displays default clear button text", () => {
      wrapper = mount(FilterContainer, {
        props: {
          showClearButton: true,
        },
      });

      expect(wrapper.find(".clear-filters-btn").text()).toBe("Clear Filters");
    });

    it("displays custom clear button text", () => {
      wrapper = mount(FilterContainer, {
        props: {
          showClearButton: true,
          clearButtonText: "Reset All",
        },
      });

      expect(wrapper.find(".clear-filters-btn").text()).toBe("Reset All");
    });

    it("emits clear event when clear button is clicked", async () => {
      wrapper = mount(FilterContainer, {
        props: {
          showClearButton: true,
        },
      });

      const clearButton = wrapper.find(".clear-filters-btn");
      await clearButton.trigger("click");

      expect(wrapper.emitted("clear")).toBeTruthy();
      expect(wrapper.emitted("clear")).toHaveLength(1);
    });

    it("hides clear button when showClearButton changes to false", async () => {
      wrapper = mount(FilterContainer, {
        props: {
          showClearButton: true,
        },
      });

      expect(wrapper.find(".clear-filters-btn").exists()).toBe(true);

      await wrapper.setProps({ showClearButton: false });

      expect(wrapper.find(".clear-filters-btn").exists()).toBe(false);
    });

    it("shows clear button when showClearButton changes to true", async () => {
      wrapper = mount(FilterContainer, {
        props: {
          showClearButton: false,
        },
      });

      expect(wrapper.find(".clear-filters-btn").exists()).toBe(false);

      await wrapper.setProps({ showClearButton: true });

      expect(wrapper.find(".clear-filters-btn").exists()).toBe(true);
    });
  });

  describe("Integration Scenarios", () => {
    it("works with filter components in slot", () => {
      wrapper = mount(FilterContainer, {
        props: {
          title: "Filters",
          showClearButton: true,
        },
        slots: {
          default: `
            <div class="filter-item">
              <label>Filter 1</label>
              <select><option>Option 1</option></select>
            </div>
            <div class="filter-item">
              <label>Filter 2</label>
              <input type="text" />
            </div>
          `,
        },
      });

      expect(wrapper.findAll(".filter-item")).toHaveLength(2);
      expect(wrapper.find("select").exists()).toBe(true);
      expect(wrapper.find("input").exists()).toBe(true);
    });

    it("handles clear event with parent component", async () => {
      const clearHandler = jest.fn();

      wrapper = mount(FilterContainer, {
        props: {
          showClearButton: true,
          onClear: clearHandler,
        },
      });

      const clearButton = wrapper.find(".clear-filters-btn");
      await clearButton.trigger("click");

      expect(clearHandler).toHaveBeenCalledTimes(1);
    });

    it("updates button text dynamically", async () => {
      wrapper = mount(FilterContainer, {
        props: {
          showClearButton: true,
          clearButtonText: "Reset",
        },
      });

      expect(wrapper.find(".clear-filters-btn").text()).toBe("Reset");

      await wrapper.setProps({ clearButtonText: "Clear All" });

      expect(wrapper.find(".clear-filters-btn").text()).toBe("Clear All");
    });
  });

  describe("Edge Cases", () => {
    it("handles empty slot", () => {
      wrapper = mount(FilterContainer, {
        props: {
          title: "Empty",
        },
      });

      const container = wrapper.find(".filter-container");
      expect(container.exists()).toBe(true);
    });

    it("handles very long title", () => {
      const longTitle = "A".repeat(200);
      wrapper = mount(FilterContainer, {
        props: {
          title: longTitle,
        },
      });

      expect(wrapper.find(".filter-title").text()).toBe(longTitle);
    });

    it("handles special characters in title", () => {
      wrapper = mount(FilterContainer, {
        props: {
          title: "Filter & Sort <Options>",
        },
      });

      expect(wrapper.find(".filter-title").text()).toBe(
        "Filter & Sort <Options>",
      );
    });

    it("handles special characters in button text", () => {
      wrapper = mount(FilterContainer, {
        props: {
          showClearButton: true,
          clearButtonText: "Clear & Reset <All>",
        },
      });

      expect(wrapper.find(".clear-filters-btn").text()).toBe(
        "Clear & Reset <All>",
      );
    });

    it("handles rapid toggling of clear button", async () => {
      wrapper = mount(FilterContainer, {
        props: {
          showClearButton: true,
        },
      });

      await wrapper.setProps({ showClearButton: false });
      await wrapper.setProps({ showClearButton: true });
      await wrapper.setProps({ showClearButton: false });
      await wrapper.setProps({ showClearButton: true });

      expect(wrapper.find(".clear-filters-btn").exists()).toBe(true);
    });
  });

  describe("Accessibility", () => {
    it("button is keyboard accessible", async () => {
      wrapper = mount(FilterContainer, {
        props: {
          showClearButton: true,
        },
      });

      const button = wrapper.find("button");
      expect(button.attributes("type")).toBeUndefined(); // Default button type

      // Should be clickable (trigger works)
      await button.trigger("click");
      expect(wrapper.emitted("clear")).toBeTruthy();
    });
  });

  describe("Slot Content Reactivity", () => {
    it("updates slot content when props change", async () => {
      wrapper = mount(FilterContainer, {
        props: {
          title: "Initial",
        },
        slots: {
          default: '<div class="content">{{ message }}</div>',
        },
      });

      await wrapper.setProps({ title: "Updated" });

      expect(wrapper.find(".filter-title").text()).toBe("Updated");
    });

    it("preserves slot content when showClearButton toggles", async () => {
      wrapper = mount(FilterContainer, {
        props: {
          showClearButton: false,
        },
        slots: {
          default: '<div class="persistent">Persistent Content</div>',
        },
      });

      expect(wrapper.find(".persistent").exists()).toBe(true);

      await wrapper.setProps({ showClearButton: true });

      expect(wrapper.find(".persistent").exists()).toBe(true);
      expect(wrapper.find(".persistent").text()).toBe("Persistent Content");
    });
  });
});
