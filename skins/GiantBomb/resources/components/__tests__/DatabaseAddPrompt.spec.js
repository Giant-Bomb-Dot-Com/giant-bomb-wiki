const { mount } = require("@vue/test-utils");
const DatabaseAddPrompt = require("../DatabaseAddPrompt.vue");

describe("DatabaseAddPrompt", () => {
  const defaultProps = {
    objectType: "platform",
    linkUrl: "/wiki/Form:Platform",
  };

  describe("Initial Render", () => {
    it("renders the component with proper title", async () => {
      const wrapper = mount(DatabaseAddPrompt, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const title = wrapper.find(".prompt-title");
      expect(title.exists()).toBe(true);
      expect(title.text()).toBe("Add to the database");
    });

    it("renders the prompt text with object type", async () => {
      const wrapper = mount(DatabaseAddPrompt, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const text = wrapper.find(".prompt-text");
      expect(text.exists()).toBe(true);
      expect(text.text()).toContain("platform");
      expect(text.text()).toContain("Are we missing something?");
    });

    it("renders the add button with correct text", async () => {
      const wrapper = mount(DatabaseAddPrompt, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const button = wrapper.find(".add-button");
      expect(button.exists()).toBe(true);
      expect(button.text()).toContain("Create new platform page");
    });

    it("renders the add button with correct href", async () => {
      const wrapper = mount(DatabaseAddPrompt, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const button = wrapper.find(".add-button");
      expect(button.attributes("href")).toBe("/wiki/Form:Platform");
    });
  });

  describe("Props Handling", () => {
    it("accepts different object types", async () => {
      const wrapper = mount(DatabaseAddPrompt, {
        props: {
          objectType: "game",
          linkUrl: "/wiki/Form:Game",
        },
      });

      await wrapper.vm.$nextTick();

      const text = wrapper.find(".prompt-text");
      expect(text.text()).toContain("game");

      const button = wrapper.find(".add-button");
      expect(button.text()).toContain("Create new game page");
    });

    it("uses custom link URL", async () => {
      const customUrl = "/wiki/Special:FormEdit/Platform";
      const wrapper = mount(DatabaseAddPrompt, {
        props: {
          objectType: "platform",
          linkUrl: customUrl,
        },
      });

      await wrapper.vm.$nextTick();

      const button = wrapper.find(".add-button");
      expect(button.attributes("href")).toBe(customUrl);
    });

    it("handles different object types in button text", async () => {
      const testCases = [
        { objectType: "character", expected: "Create new character page" },
        { objectType: "concept", expected: "Create new concept page" },
        { objectType: "location", expected: "Create new location page" },
      ];

      for (const testCase of testCases) {
        const wrapper = mount(DatabaseAddPrompt, {
          props: {
            objectType: testCase.objectType,
            linkUrl: "#",
          },
        });

        await wrapper.vm.$nextTick();

        const button = wrapper.find(".add-button");
        expect(button.text()).toBe(testCase.expected);
      }
    });
  });

  describe("Edge Cases", () => {
    it("handles empty object type gracefully", async () => {
      const wrapper = mount(DatabaseAddPrompt, {
        props: {
          objectType: "",
          linkUrl: "/wiki/Form:Test",
        },
      });

      await wrapper.vm.$nextTick();

      // Should still render without crashing
      expect(wrapper.find(".database-add-prompt").exists()).toBe(true);
    });

    it("handles special characters in object type", async () => {
      const wrapper = mount(DatabaseAddPrompt, {
        props: {
          objectType: "game/dlc",
          linkUrl: "#",
        },
      });

      await wrapper.vm.$nextTick();

      const text = wrapper.find(".prompt-text");
      expect(text.text()).toContain("game/dlc");
    });
  });
});
