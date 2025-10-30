const { mount } = require("@vue/test-utils");
const GameTags = require("../GameTags.vue");

describe("GameTags", () => {
  const defaultProps = {
    tags: "Action, Adventure, RPG",
  };

  describe("Initial Render", () => {
    it("renders all tags from comma-separated string", () => {
      const wrapper = mount(GameTags, {
        props: defaultProps,
      });

      const tagLinks = wrapper.findAll(".game-tag");
      expect(tagLinks).toHaveLength(3);
      expect(tagLinks[0].text()).toBe("Action");
      expect(tagLinks[1].text()).toBe("Adventure");
      expect(tagLinks[2].text()).toBe("RPG");
    });

    it("applies default tag type class", () => {
      const wrapper = mount(GameTags, {
        props: defaultProps,
      });

      const tagLinks = wrapper.findAll(".game-tag");
      tagLinks.forEach((tag) => {
        expect(tag.classes()).toContain("game-tag--default");
      });
    });
  });

  describe("Tag URLs", () => {
    it("generates search URLs when no namespace is provided", () => {
      const wrapper = mount(GameTags, {
        props: defaultProps,
      });

      const firstTag = wrapper.find(".game-tag");
      expect(firstTag.attributes("href")).toBe(
        "/index.php/Special:Search?search=Action",
      );
    });

    it("generates namespace URLs when namespace is provided", () => {
      const wrapper = mount(GameTags, {
        props: {
          ...defaultProps,
          namespace: "Genre",
        },
      });

      const tagLinks = wrapper.findAll(".game-tag");
      expect(tagLinks[0].attributes("href")).toBe("/index.php/Genre/Action");
      expect(tagLinks[1].attributes("href")).toBe("/index.php/Genre/Adventure");
      expect(tagLinks[2].attributes("href")).toBe("/index.php/Genre/RPG");
    });

    it("handles tags with spaces correctly in namespace URLs", () => {
      const wrapper = mount(GameTags, {
        props: {
          tags: "First Person Shooter",
          namespace: "Genre",
        },
      });

      const firstTag = wrapper.find(".game-tag");
      expect(firstTag.attributes("href")).toBe(
        "/index.php/Genre/First_Person_Shooter",
      );
    });
  });

  describe("Tag Interactions", () => {
    it("marks clicked tag as active", async () => {
      const wrapper = mount(GameTags, {
        props: defaultProps,
      });

      const secondTag = wrapper.findAll(".game-tag")[1];
      await secondTag.trigger("click");

      expect(secondTag.classes()).toContain("game-tag--active");
    });

    it("constructs correct URL for navigation", () => {
      const wrapper = mount(GameTags, {
        props: {
          ...defaultProps,
          namespace: "Genre",
        },
      });

      const firstTag = wrapper.find(".game-tag");
      expect(firstTag.attributes("href")).toBe("/index.php/Genre/Action");
    });

    it("only one tag can be active at a time", async () => {
      const wrapper = mount(GameTags, {
        props: defaultProps,
      });

      const tagLinks = wrapper.findAll(".game-tag");

      await tagLinks[0].trigger("click");
      expect(tagLinks[0].classes()).toContain("game-tag--active");

      await tagLinks[1].trigger("click");
      expect(tagLinks[0].classes()).not.toContain("game-tag--active");
      expect(tagLinks[1].classes()).toContain("game-tag--active");
    });
  });

  describe("Edge Cases", () => {
    it("handles tags with extra whitespace", () => {
      const wrapper = mount(GameTags, {
        props: {
          tags: "  Action  ,  Adventure  ,  RPG  ",
        },
      });

      const tagLinks = wrapper.findAll(".game-tag");
      expect(tagLinks[0].text()).toBe("Action");
      expect(tagLinks[1].text()).toBe("Adventure");
      expect(tagLinks[2].text()).toBe("RPG");
    });

    it("handles single tag", () => {
      const wrapper = mount(GameTags, {
        props: {
          tags: "Action",
        },
      });

      const tagLinks = wrapper.findAll(".game-tag");
      expect(tagLinks).toHaveLength(1);
      expect(tagLinks[0].text()).toBe("Action");
    });

    it("handles empty tag string", () => {
      const wrapper = mount(GameTags, {
        props: {
          tags: "",
        },
      });

      const tagLinks = wrapper.findAll(".game-tag");
      expect(tagLinks).toHaveLength(0);
    });
  });
});
