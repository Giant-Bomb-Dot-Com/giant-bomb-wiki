const { mount } = require("@vue/test-utils");
const FilterDropdown = require("../FilterDropdown.vue");

describe("FilterDropdown", () => {
  let wrapper;

  const defaultProps = {
    id: "test-dropdown",
    label: "Test Dropdown",
    modelValue: "",
    options: ["Option 1", "Option 2", "Option 3"],
  };

  afterEach(() => {
    if (wrapper) {
      wrapper.unmount();
    }
  });

  describe("Component Rendering", () => {
    it("renders with default props", () => {
      wrapper = mount(FilterDropdown, {
        props: defaultProps,
      });

      expect(wrapper.find(".filter-group").exists()).toBe(true);
      expect(wrapper.find("label").text()).toBe("Test Dropdown");
      expect(wrapper.find("select").attributes("id")).toBe("test-dropdown");
    });

    it("renders without label when not provided", () => {
      wrapper = mount(FilterDropdown, {
        props: {
          ...defaultProps,
          label: "",
        },
      });

      expect(wrapper.find("label").exists()).toBe(false);
    });

    it("renders with placeholder option when placeholder is provided", () => {
      wrapper = mount(FilterDropdown, {
        props: {
          ...defaultProps,
          placeholder: "Select an option",
        },
      });

      const options = wrapper.findAll("option");
      expect(options[0].text()).toBe("Select an option");
      expect(options[0].attributes("value")).toBe("");
    });

    it("renders without placeholder option when placeholder is not provided", () => {
      wrapper = mount(FilterDropdown, {
        props: defaultProps,
      });

      const options = wrapper.findAll("option");
      expect(options).toHaveLength(3);
      expect(options[0].text()).toBe("Option 1");
    });

    it("renders all options from string array", () => {
      wrapper = mount(FilterDropdown, {
        props: defaultProps,
      });

      const options = wrapper.findAll("option");
      expect(options).toHaveLength(3);
      expect(options[0].text()).toBe("Option 1");
      expect(options[1].text()).toBe("Option 2");
      expect(options[2].text()).toBe("Option 3");
    });
  });

  describe("String Options", () => {
    it("handles string array options correctly", () => {
      wrapper = mount(FilterDropdown, {
        props: {
          ...defaultProps,
          options: ["Apple", "Banana", "Cherry"],
        },
      });

      const options = wrapper.findAll("option");
      expect(options).toHaveLength(3);
      expect(options[0].attributes("value")).toBe("Apple");
      expect(options[0].text()).toBe("Apple");
    });

    it("sets selected value from modelValue with string options", () => {
      wrapper = mount(FilterDropdown, {
        props: {
          ...defaultProps,
          modelValue: "Option 2",
        },
      });

      const select = wrapper.find("select");
      expect(select.element.value).toBe("Option 2");
    });
  });

  describe("Object Options", () => {
    it("renders object array options with valueKey and labelKey", () => {
      const objectOptions = [
        { id: "1", name: "First Option" },
        { id: "2", name: "Second Option" },
        { id: "3", name: "Third Option" },
      ];

      wrapper = mount(FilterDropdown, {
        props: {
          ...defaultProps,
          options: objectOptions,
          valueKey: "id",
          labelKey: "name",
        },
      });

      const options = wrapper.findAll("option");
      expect(options).toHaveLength(3);
      expect(options[0].attributes("value")).toBe("1");
      expect(options[0].text()).toBe("First Option");
      expect(options[1].attributes("value")).toBe("2");
      expect(options[1].text()).toBe("Second Option");
    });

    it("sets selected value from modelValue with object options", () => {
      const objectOptions = [
        { id: "1", name: "First" },
        { id: "2", name: "Second" },
      ];

      wrapper = mount(FilterDropdown, {
        props: {
          ...defaultProps,
          options: objectOptions,
          valueKey: "id",
          labelKey: "name",
          modelValue: "2",
        },
      });

      const select = wrapper.find("select");
      expect(select.element.value).toBe("2");
    });

    it("handles objects with different key names", () => {
      const objectOptions = [
        { value: "red", label: "Red Color" },
        { value: "blue", label: "Blue Color" },
      ];

      wrapper = mount(FilterDropdown, {
        props: {
          ...defaultProps,
          options: objectOptions,
          valueKey: "value",
          labelKey: "label",
        },
      });

      const options = wrapper.findAll("option");
      expect(options[0].attributes("value")).toBe("red");
      expect(options[0].text()).toBe("Red Color");
      expect(options[1].attributes("value")).toBe("blue");
      expect(options[1].text()).toBe("Blue Color");
    });

    it("falls back to using whole object when keys are not specified", () => {
      const objectOptions = [
        { id: "1", name: "First" },
        { id: "2", name: "Second" },
      ];

      wrapper = mount(FilterDropdown, {
        props: {
          ...defaultProps,
          options: objectOptions,
        },
      });

      const options = wrapper.findAll("option");
      // Without valueKey/labelKey, it should use the object itself
      expect(options[0].attributes("value")).toBe("[object Object]");
    });
  });

  describe("Value Updates", () => {
    it("emits update:modelValue when option is selected", async () => {
      wrapper = mount(FilterDropdown, {
        props: defaultProps,
      });

      const select = wrapper.find("select");
      await select.setValue("Option 2");

      expect(wrapper.emitted("update:modelValue")).toBeTruthy();
      expect(wrapper.emitted("update:modelValue")[0][0]).toBe("Option 2");
    });

    it("emits update:modelValue with correct value for object options", async () => {
      const objectOptions = [
        { id: "1", name: "First" },
        { id: "2", name: "Second" },
      ];

      wrapper = mount(FilterDropdown, {
        props: {
          ...defaultProps,
          options: objectOptions,
          valueKey: "id",
          labelKey: "name",
        },
      });

      const select = wrapper.find("select");
      await select.setValue("2");

      expect(wrapper.emitted("update:modelValue")).toBeTruthy();
      expect(wrapper.emitted("update:modelValue")[0][0]).toBe("2");
    });

    it("emits update:modelValue when clearing selection", async () => {
      wrapper = mount(FilterDropdown, {
        props: {
          ...defaultProps,
          modelValue: "Option 2",
          placeholder: "All Options",
        },
      });

      const select = wrapper.find("select");
      await select.setValue("");

      expect(wrapper.emitted("update:modelValue")).toBeTruthy();
      expect(wrapper.emitted("update:modelValue")[0][0]).toBe("");
    });

    it("updates displayed value when modelValue prop changes", async () => {
      wrapper = mount(FilterDropdown, {
        props: {
          ...defaultProps,
          modelValue: "Option 1",
        },
      });

      expect(wrapper.find("select").element.value).toBe("Option 1");

      await wrapper.setProps({ modelValue: "Option 3" });

      expect(wrapper.find("select").element.value).toBe("Option 3");
    });
  });

  describe("Number Values", () => {
    it("handles number type for modelValue", () => {
      wrapper = mount(FilterDropdown, {
        props: {
          ...defaultProps,
          modelValue: 30,
          options: [10, 20, 30, 40, 50],
        },
      });
      expect(wrapper.find("select").element.value).toBe("30");
    });

    it("emits number values correctly", async () => {
      wrapper = mount(FilterDropdown, {
        props: {
          ...defaultProps,
          options: ["10", "20", "30"],
        },
      });

      const select = wrapper.find("select");
      await select.setValue("20");

      expect(wrapper.emitted("update:modelValue")[0][0]).toBe("20");
    });
  });

  describe("Accessibility", () => {
    it("associates label with select using for/id attributes", () => {
      wrapper = mount(FilterDropdown, {
        props: defaultProps,
      });

      const label = wrapper.find("label");
      const select = wrapper.find("select");

      expect(label.attributes("for")).toBe("test-dropdown");
      expect(select.attributes("id")).toBe("test-dropdown");
    });
  });

  describe("Edge Cases", () => {
    it("handles empty options array", () => {
      wrapper = mount(FilterDropdown, {
        props: {
          ...defaultProps,
          options: [],
        },
      });

      const options = wrapper.findAll("option");
      expect(options).toHaveLength(0);
    });

    it("handles single option", () => {
      wrapper = mount(FilterDropdown, {
        props: {
          ...defaultProps,
          options: ["Only Option"],
        },
      });

      const options = wrapper.findAll("option");
      expect(options).toHaveLength(1);
      expect(options[0].text()).toBe("Only Option");
    });

    it("handles options with special characters", () => {
      wrapper = mount(FilterDropdown, {
        props: {
          ...defaultProps,
          options: ["Option & Value", "Option <> Value", 'Option "Value"'],
        },
      });

      const options = wrapper.findAll("option");
      expect(options[0].text()).toBe("Option & Value");
      expect(options[1].text()).toBe("Option <> Value");
      expect(options[2].text()).toBe('Option "Value"');
    });

    it("handles very long option text", () => {
      const longText = "A".repeat(200);
      wrapper = mount(FilterDropdown, {
        props: {
          ...defaultProps,
          options: [longText],
        },
      });

      const option = wrapper.find("option");
      expect(option.text()).toBe(longText);
    });

    it("handles null valueKey with objects", () => {
      const objectOptions = [{ id: 1, name: "Test" }];

      wrapper = mount(FilterDropdown, {
        props: {
          ...defaultProps,
          options: objectOptions,
          valueKey: null,
        },
      });

      // Should still render but use object.toString()
      const options = wrapper.findAll("option");
      expect(options).toHaveLength(1);
    });

    it("maintains selection when options change", async () => {
      wrapper = mount(FilterDropdown, {
        props: {
          ...defaultProps,
          modelValue: "Option 2",
        },
      });

      expect(wrapper.find("select").element.value).toBe("Option 2");

      await wrapper.setProps({
        options: ["New 1", "Option 2", "New 3"],
      });

      expect(wrapper.find("select").element.value).toBe("Option 2");
    });
  });

  describe("Helper Methods", () => {
    it("getOptionValue returns correct value for strings", () => {
      wrapper = mount(FilterDropdown, {
        props: defaultProps,
      });

      expect(wrapper.vm.getOptionValue("test")).toBe("test");
    });

    it("getOptionValue returns correct value for objects with valueKey", () => {
      wrapper = mount(FilterDropdown, {
        props: {
          ...defaultProps,
          options: [{ id: "123", name: "Test" }],
          valueKey: "id",
        },
      });

      expect(wrapper.vm.getOptionValue({ id: "123", name: "Test" })).toBe(
        "123",
      );
    });

    it("getOptionLabel returns correct label for strings", () => {
      wrapper = mount(FilterDropdown, {
        props: defaultProps,
      });

      expect(wrapper.vm.getOptionLabel("test")).toBe("test");
    });

    it("getOptionLabel returns correct label for objects with labelKey", () => {
      wrapper = mount(FilterDropdown, {
        props: {
          ...defaultProps,
          options: [{ id: "123", name: "Test Label" }],
          labelKey: "name",
        },
      });

      expect(wrapper.vm.getOptionLabel({ id: "123", name: "Test Label" })).toBe(
        "Test Label",
      );
    });
  });

  describe("Integration Scenarios", () => {
    it("works with v-model pattern", async () => {
      let selectedValue = "";

      wrapper = mount(FilterDropdown, {
        props: {
          ...defaultProps,
          modelValue: selectedValue,
          "onUpdate:modelValue": (value) => {
            selectedValue = value;
            wrapper.setProps({ modelValue: value });
          },
        },
      });

      const select = wrapper.find("select");
      await select.setValue("Option 3");

      expect(selectedValue).toBe("Option 3");
      expect(wrapper.props("modelValue")).toBe("Option 3");
    });

    it("works in a form context", async () => {
      wrapper = mount(FilterDropdown, {
        props: {
          ...defaultProps,
          modelValue: "Option 1",
        },
      });

      const select = wrapper.find("select");
      expect(select.element.value).toBe("Option 1");

      // Simulate form change
      await select.setValue("Option 2");

      expect(wrapper.emitted("update:modelValue")[0][0]).toBe("Option 2");
    });
  });
});
