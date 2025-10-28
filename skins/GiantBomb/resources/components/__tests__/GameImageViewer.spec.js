const { mount } = require("@vue/test-utils");
const GameImageViewer = require("../GameImageViewer.vue");

describe("GameImageViewer", () => {
  const defaultProps = {
    imageUrl: "https://example.com/test-image.jpg",
    altText: "Test game image",
  };

  beforeEach(() => {
    document.body.style.overflow = "";
  });

  describe("Initial Render", () => {
    it("renders the image with correct src and alt", () => {
      const wrapper = mount(GameImageViewer, {
        props: defaultProps,
      });

      const img = wrapper.find(".game-image");
      expect(img.exists()).toBe(true);
      expect(img.attributes("src")).toBe(defaultProps.imageUrl);
      expect(img.attributes("alt")).toBe(defaultProps.altText);
    });

    it("uses default alt text when not provided", () => {
      const wrapper = mount(GameImageViewer, {
        props: {
          imageUrl: defaultProps.imageUrl,
        },
      });

      const img = wrapper.find(".game-image");
      expect(img.attributes("alt")).toBe("Game image");
    });
  });

  describe("Fullscreen Toggle", () => {
    it("opens fullscreen when image container is clicked", async () => {
      const wrapper = mount(GameImageViewer, {
        props: defaultProps,
      });

      const container = wrapper.find(".game-image-container");
      await container.trigger("click");

      const fullscreen = wrapper.find(".game-image-fullscreen");
      expect(fullscreen.exists()).toBe(true);
      expect(document.body.style.overflow).toBe("hidden");
    });

    it("closes fullscreen when close button is clicked", async () => {
      const wrapper = mount(GameImageViewer, {
        props: defaultProps,
      });

      await wrapper.find(".game-image-container").trigger("click");
      expect(wrapper.find(".game-image-fullscreen").exists()).toBe(true);

      await wrapper.find(".game-image-close").trigger("click");
      expect(wrapper.find(".game-image-fullscreen").exists()).toBe(false);
      expect(document.body.style.overflow).toBe("");
    });
  });
});
