const { mount } = require("@vue/test-utils");
const ViewToggle = require("../ViewToggle.vue");

describe("ViewToggle", () => {
  const defaultProps = {
    targetContainer: ".releases-page",
    storageKey: "testViewPreference",
  };

  let mockContainer;

  beforeEach(() => {
    // Create a mock container element
    mockContainer = document.createElement("div");
    mockContainer.className = "releases-page";
    document.body.appendChild(mockContainer);

    // Mock localStorage
    const localStorageMock = (() => {
      let store = {};
      return {
        getItem: jest.fn((key) => store[key] || null),
        setItem: jest.fn((key, value) => {
          store[key] = value.toString();
        }),
        clear: jest.fn(() => {
          store = {};
        }),
        removeItem: jest.fn((key) => {
          delete store[key];
        }),
      };
    })();

    Object.defineProperty(window, "localStorage", {
      value: localStorageMock,
      writable: true,
    });
  });

  afterEach(() => {
    // Clean up DOM
    if (mockContainer && mockContainer.parentNode) {
      mockContainer.parentNode.removeChild(mockContainer);
    }
    jest.clearAllMocks();
  });

  describe("Initial Render", () => {
    it("renders both list and grid buttons", () => {
      const wrapper = mount(ViewToggle, {
        props: defaultProps,
      });

      const buttons = wrapper.findAll(".view-btn");
      expect(buttons).toHaveLength(2);

      const listButton = wrapper.find(".view-list");
      const gridButton = wrapper.find(".view-grid");

      expect(listButton.exists()).toBe(true);
      expect(gridButton.exists()).toBe(true);
      expect(listButton.text()).toContain("List");
      expect(gridButton.text()).toContain("Grid");
    });

    it("renders SVG icons in buttons", () => {
      const wrapper = mount(ViewToggle, {
        props: defaultProps,
      });

      const svgs = wrapper.findAll("svg");
      expect(svgs).toHaveLength(2);
    });

    it("sets grid as active by default", async () => {
      const wrapper = mount(ViewToggle, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      expect(wrapper.vm.currentView).toBe("grid");

      const gridButton = wrapper.find(".view-grid");
      expect(gridButton.classes()).toContain("active");

      const listButton = wrapper.find(".view-list");
      expect(listButton.classes()).not.toContain("active");
    });

    it("applies grid class to target container on mount", async () => {
      const wrapper = mount(ViewToggle, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      expect(mockContainer.classList.contains("view-grid")).toBe(true);
    });
  });

  describe("View Switching", () => {
    it("switches to list view when list button is clicked", async () => {
      const wrapper = mount(ViewToggle, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const listButton = wrapper.find(".view-list");
      await listButton.trigger("click");

      expect(wrapper.vm.currentView).toBe("list");
      expect(listButton.classes()).toContain("active");

      const gridButton = wrapper.find(".view-grid");
      expect(gridButton.classes()).not.toContain("active");
    });

    it("switches to grid view when grid button is clicked", async () => {
      const wrapper = mount(ViewToggle, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Switch to list first
      const listButton = wrapper.find(".view-list");
      await listButton.trigger("click");

      // Then switch back to grid
      const gridButton = wrapper.find(".view-grid");
      await gridButton.trigger("click");

      expect(wrapper.vm.currentView).toBe("grid");
      expect(gridButton.classes()).toContain("active");
      expect(listButton.classes()).not.toContain("active");
    });

    it("updates target container class when switching views", async () => {
      const wrapper = mount(ViewToggle, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const listButton = wrapper.find(".view-list");
      await listButton.trigger("click");

      expect(mockContainer.classList.contains("view-list")).toBe(true);
      expect(mockContainer.classList.contains("view-grid")).toBe(false);

      const gridButton = wrapper.find(".view-grid");
      await gridButton.trigger("click");

      expect(mockContainer.classList.contains("view-grid")).toBe(true);
      expect(mockContainer.classList.contains("view-list")).toBe(false);
    });

    it("removes previous view class before adding new one", async () => {
      const wrapper = mount(ViewToggle, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Initially has view-grid
      expect(mockContainer.classList.contains("view-grid")).toBe(true);

      // Switch to list
      await wrapper.find(".view-list").trigger("click");
      expect(mockContainer.classList.contains("view-list")).toBe(true);
      expect(mockContainer.classList.contains("view-grid")).toBe(false);

      // Switch back to grid
      await wrapper.find(".view-grid").trigger("click");
      expect(mockContainer.classList.contains("view-grid")).toBe(true);
      expect(mockContainer.classList.contains("view-list")).toBe(false);
    });
  });

  describe("LocalStorage Integration", () => {
    it("saves view preference to localStorage when changed", async () => {
      const wrapper = mount(ViewToggle, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const listButton = wrapper.find(".view-list");
      await listButton.trigger("click");

      expect(localStorage.setItem).toHaveBeenCalledWith(
        "testViewPreference",
        "list",
      );
    });

    it("loads saved view preference from localStorage on mount", async () => {
      localStorage.setItem("testViewPreference", "list");

      const wrapper = mount(ViewToggle, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      expect(wrapper.vm.currentView).toBe("list");
      expect(mockContainer.classList.contains("view-list")).toBe(true);

      const listButton = wrapper.find(".view-list");
      expect(listButton.classes()).toContain("active");
    });

    it("uses default grid view when no preference is saved", async () => {
      const wrapper = mount(ViewToggle, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      expect(wrapper.vm.currentView).toBe("grid");
      expect(mockContainer.classList.contains("view-grid")).toBe(true);
    });

    it("ignores invalid values from localStorage", async () => {
      localStorage.setItem("testViewPreference", "invalid");

      const wrapper = mount(ViewToggle, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Should default to grid
      expect(wrapper.vm.currentView).toBe("grid");
      expect(mockContainer.classList.contains("view-grid")).toBe(true);
    });

    it("handles localStorage errors gracefully when saving", async () => {
      const consoleWarnSpy = jest.spyOn(console, "warn").mockImplementation();

      localStorage.setItem.mockImplementation(() => {
        throw new Error("Storage quota exceeded");
      });

      const wrapper = mount(ViewToggle, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const listButton = wrapper.find(".view-list");
      await listButton.trigger("click");

      expect(consoleWarnSpy).toHaveBeenCalledWith(
        "Could not save view preference:",
        expect.any(Error),
      );

      // View should still change even if storage fails
      expect(wrapper.vm.currentView).toBe("list");

      consoleWarnSpy.mockRestore();
    });

    it("handles localStorage errors gracefully when loading", async () => {
      const consoleWarnSpy = jest.spyOn(console, "warn").mockImplementation();

      localStorage.getItem.mockImplementation(() => {
        throw new Error("Storage access denied");
      });

      const wrapper = mount(ViewToggle, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      expect(consoleWarnSpy).toHaveBeenCalledWith(
        "Could not load view preference:",
        expect.any(Error),
      );

      // Should default to grid
      expect(wrapper.vm.currentView).toBe("grid");

      consoleWarnSpy.mockRestore();
    });
  });

  describe("Props Configuration", () => {
    it("uses custom storage key when provided", async () => {
      const wrapper = mount(ViewToggle, {
        props: {
          targetContainer: ".releases-page",
          storageKey: "customKey",
        },
      });

      await wrapper.vm.$nextTick();

      const listButton = wrapper.find(".view-list");
      await listButton.trigger("click");

      expect(localStorage.setItem).toHaveBeenCalledWith("customKey", "list");
    });

    it("uses default storage key when not provided", async () => {
      const wrapper = mount(ViewToggle, {
        props: {
          targetContainer: ".releases-page",
        },
      });

      await wrapper.vm.$nextTick();

      const listButton = wrapper.find(".view-list");
      await listButton.trigger("click");

      expect(localStorage.setItem).toHaveBeenCalledWith(
        "viewPreference",
        "list",
      );
    });

    it("targets correct container based on prop", async () => {
      // Create a different container
      const altContainer = document.createElement("div");
      altContainer.className = "custom-container";
      document.body.appendChild(altContainer);

      const wrapper = mount(ViewToggle, {
        props: {
          targetContainer: ".custom-container",
          storageKey: "testKey",
        },
      });

      await wrapper.vm.$nextTick();

      expect(altContainer.classList.contains("view-grid")).toBe(true);
      expect(mockContainer.classList.contains("view-grid")).toBe(false);

      // Clean up
      document.body.removeChild(altContainer);
    });
  });

  describe("Edge Cases", () => {
    it("handles missing target container gracefully", async () => {
      const wrapper = mount(ViewToggle, {
        props: {
          targetContainer: ".non-existent",
          storageKey: "testKey",
        },
      });

      await wrapper.vm.$nextTick();

      // Should not throw error
      const listButton = wrapper.find(".view-list");
      await listButton.trigger("click");

      expect(wrapper.vm.currentView).toBe("list");
    });

    it("does not add classes if container is not found", async () => {
      // Remove the mock container
      document.body.removeChild(mockContainer);

      const wrapper = mount(ViewToggle, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Should not throw error, just fail silently
      expect(wrapper.vm.currentView).toBe("grid");
    });

    it("handles rapid view switches", async () => {
      const wrapper = mount(ViewToggle, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Rapidly switch between views
      await wrapper.find(".view-list").trigger("click");
      await wrapper.find(".view-grid").trigger("click");
      await wrapper.find(".view-list").trigger("click");
      await wrapper.find(".view-grid").trigger("click");

      expect(wrapper.vm.currentView).toBe("grid");
      expect(mockContainer.classList.contains("view-grid")).toBe(true);
      expect(mockContainer.classList.contains("view-list")).toBe(false);
    });

    it("clicking active button does not cause issues", async () => {
      const wrapper = mount(ViewToggle, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const gridButton = wrapper.find(".view-grid");

      // Click the already active button
      await gridButton.trigger("click");
      await gridButton.trigger("click");

      expect(wrapper.vm.currentView).toBe("grid");
      expect(gridButton.classes()).toContain("active");
    });
  });

  describe("Reactivity", () => {
    it("updates active state reactively", async () => {
      const wrapper = mount(ViewToggle, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const listButton = wrapper.find(".view-list");
      const gridButton = wrapper.find(".view-grid");

      // Initially grid is active
      expect(gridButton.classes()).toContain("active");
      expect(listButton.classes()).not.toContain("active");

      // Switch to list
      await listButton.trigger("click");
      await wrapper.vm.$nextTick();

      expect(listButton.classes()).toContain("active");
      expect(gridButton.classes()).not.toContain("active");

      // Switch back to grid
      await gridButton.trigger("click");
      await wrapper.vm.$nextTick();

      expect(gridButton.classes()).toContain("active");
      expect(listButton.classes()).not.toContain("active");
    });
  });
});
