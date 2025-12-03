const { mount } = require("@vue/test-utils");
const GameFilter = require("../GameFilter.vue");

describe("GameFilter", () => {
  let wrapper;
  const mockPlatforms = [
    { name: "PlayStation 5", displayName: "PlayStation 5" },
    { name: "Xbox Series X", displayName: "Xbox Series X" },
    { name: "PC", displayName: "PC" },
    { name: "Nintendo Switch", displayName: "Nintendo Switch" },
  ];
  let eventListenerSpy;

  beforeEach(() => {
    // Use fake timers for debounce testing
    jest.useFakeTimers();
    // Clear URL parameters before each test
    window.history.replaceState({}, "", "http://localhost/");
    // Mock window.history.pushState
    jest.spyOn(window.history, "pushState").mockImplementation(() => {});
    // Spy on window.dispatchEvent to track CustomEvents
    eventListenerSpy = jest.spyOn(window, "dispatchEvent");
  });

  afterEach(() => {
    if (wrapper) {
      wrapper.unmount();
    }
    jest.useRealTimers();
    jest.restoreAllMocks();
  });

  describe("Initial Render", () => {
    it("renders the filter title", () => {
      wrapper = mount(GameFilter, {
        props: {
          platformsData: JSON.stringify(mockPlatforms),
        },
      });

      expect(wrapper.find(".filter-title").text()).toBe("Filter Games");
    });

    it("renders all filter inputs with correct labels", () => {
      wrapper = mount(GameFilter, {
        props: {
          platformsData: JSON.stringify(mockPlatforms),
        },
      });

      const labels = wrapper.findAll("label");
      expect(labels).toHaveLength(3);
      expect(labels[0].text()).toBe("Search");
      expect(labels[1].text()).toBe("Platform");
      expect(labels[2].text()).toBe("Sort By");
    });

    it("renders search input", () => {
      wrapper = mount(GameFilter, {
        props: {
          platformsData: JSON.stringify(mockPlatforms),
        },
      });

      const searchInput = wrapper.find("#search-filter");
      expect(searchInput.exists()).toBe(true);
      expect(searchInput.element.value).toBe("");
    });

    it("renders platform dropdown with all platforms", async () => {
      wrapper = mount(GameFilter, {
        props: {
          platformsData: JSON.stringify(mockPlatforms),
        },
      });

      await wrapper.vm.$nextTick();

      const platformSelect = wrapper.find("#platform-filter");
      expect(platformSelect.exists()).toBe(true);

      const options = platformSelect.findAll("option");
      expect(options).toHaveLength(mockPlatforms.length + 1); // +1 for "All Platforms"
      expect(options[0].text()).toBe("All Platforms");
      expect(options[1].text()).toBe("PlayStation 5");
      expect(options[2].text()).toBe("Xbox Series X");
      expect(options[3].text()).toBe("PC");
      expect(options[4].text()).toBe("Nintendo Switch");
    });

    it("renders sort dropdown with all sort options", () => {
      wrapper = mount(GameFilter, {
        props: {
          platformsData: JSON.stringify(mockPlatforms),
        },
      });

      const sortSelect = wrapper.find("#sort-filter");
      expect(sortSelect.exists()).toBe(true);

      const options = sortSelect.findAll("option");
      expect(options).toHaveLength(4);
      expect(options[0].text()).toBe("Title (A-Z)");
      expect(options[0].element.value).toBe("title-asc");
      expect(options[1].text()).toBe("Title (Z-A)");
      expect(options[1].element.value).toBe("title-desc");
      expect(options[2].text()).toBe("Newest First");
      expect(options[2].element.value).toBe("date-desc");
      expect(options[3].text()).toBe("Oldest First");
      expect(options[3].element.value).toBe("date-asc");
    });
  });

  describe("Filter Selection", () => {
    it("updates platform filter value and dispatches event", async () => {
      wrapper = mount(GameFilter, {
        props: {
          platformsData: JSON.stringify(mockPlatforms),
        },
      });

      await wrapper.vm.$nextTick();

      const platformSelect = wrapper.find("#platform-filter");
      await platformSelect.setValue("PlayStation 5");

      // Verify the component state updated
      expect(wrapper.vm.selectedPlatform).toBe("PlayStation 5");

      // Verify event was dispatched with correct details
      expect(window.dispatchEvent).toHaveBeenCalledWith(
        expect.objectContaining({
          type: "games-filter-changed",
          detail: {
            search: "",
            platform: "PlayStation 5",
            sort: "title-asc",
            page: 1,
          },
        }),
      );
    });

    it("updates sort filter value and dispatches event", async () => {
      wrapper = mount(GameFilter, {
        props: {
          platformsData: JSON.stringify(mockPlatforms),
        },
      });

      const sortSelect = wrapper.find("#sort-filter");
      await sortSelect.setValue("date-desc");

      // Verify the component state updated
      expect(wrapper.vm.selectedSort).toBe("date-desc");

      // Verify event was dispatched with correct details
      expect(window.dispatchEvent).toHaveBeenCalledWith(
        expect.objectContaining({
          type: "games-filter-changed",
          detail: {
            search: "",
            platform: "",
            sort: "date-desc",
            page: 1,
          },
        }),
      );
    });

    it("loads filters from URL parameters on mount", async () => {
      // Set URL parameters
      window.history.replaceState(
        {},
        "",
        "?search=sonic&platform=PC&sort=title-desc",
      );

      wrapper = mount(GameFilter, {
        props: {
          platformsData: JSON.stringify(mockPlatforms),
        },
      });

      await wrapper.vm.$nextTick();

      expect(wrapper.vm.searchQuery).toBe("sonic");
      expect(wrapper.vm.selectedPlatform).toBe("PC");
      expect(wrapper.vm.selectedSort).toBe("title-desc");
    });
  });

  describe("Clear Filters", () => {
    it("shows clear button when filters are active", async () => {
      wrapper = mount(GameFilter, {
        props: {
          platformsData: JSON.stringify(mockPlatforms),
        },
      });

      // Initially no clear button
      expect(wrapper.find(".clear-filters-btn").exists()).toBe(false);

      // Add a filter (set value without triggering navigation)
      wrapper.vm.searchQuery = "test";
      await wrapper.vm.$nextTick();

      // Clear button should appear
      expect(wrapper.find(".clear-filters-btn").exists()).toBe(true);
    });

    it("clears all filters when clear button is clicked", async () => {
      // Set URL parameters first
      window.history.replaceState(
        {},
        "",
        "?search=test&platform=PC&sort=date-desc",
      );

      wrapper = mount(GameFilter, {
        props: {
          platformsData: JSON.stringify(mockPlatforms),
        },
      });

      await wrapper.vm.$nextTick();

      // Verify filters are loaded from URL
      expect(wrapper.vm.searchQuery).toBe("test");
      expect(wrapper.vm.selectedPlatform).toBe("PC");
      expect(wrapper.vm.selectedSort).toBe("date-desc");

      const clearButton = wrapper.find(".clear-filters-btn");
      expect(clearButton.exists()).toBe(true);

      // Clear the spy before clicking
      eventListenerSpy.mockClear();

      // Click the clear button
      await clearButton.trigger("click");
      await wrapper.vm.$nextTick();

      // Verify filters are cleared
      expect(wrapper.vm.searchQuery).toBe("");
      expect(wrapper.vm.selectedPlatform).toBe("");
      expect(wrapper.vm.selectedSort).toBe("title-asc");

      // Verify event was dispatched with empty filters
      expect(window.dispatchEvent).toHaveBeenCalledWith(
        expect.objectContaining({
          type: "games-filter-changed",
          detail: {
            search: "",
            platform: "",
            sort: "title-asc",
            page: 1,
          },
        }),
      );
    });
  });

  describe("Async Filter Application", () => {
    it("emits games-filter-changed event when platform changes", async () => {
      wrapper = mount(GameFilter, {
        props: {
          platformsData: JSON.stringify(mockPlatforms),
        },
      });

      await wrapper.vm.$nextTick();

      const platformSelect = wrapper.find("#platform-filter");
      await platformSelect.setValue("PC");
      await platformSelect.trigger("change");

      // Verify CustomEvent was dispatched
      expect(eventListenerSpy).toHaveBeenCalled();
      const event = eventListenerSpy.mock.calls[0][0];
      expect(event.type).toBe("games-filter-changed");
      expect(event.detail).toEqual({
        search: "",
        platform: "PC",
        sort: "title-asc",
        page: 1,
      });
    });

    it("emits games-filter-changed event when sort changes", async () => {
      wrapper = mount(GameFilter, {
        props: {
          platformsData: JSON.stringify(mockPlatforms),
        },
      });

      const sortSelect = wrapper.find("#sort-filter");
      await sortSelect.setValue("date-desc");
      await sortSelect.trigger("change");

      // Verify CustomEvent was dispatched
      expect(eventListenerSpy).toHaveBeenCalled();
      const event = eventListenerSpy.mock.calls[0][0];
      expect(event.type).toBe("games-filter-changed");
      expect(event.detail).toEqual({
        search: "",
        platform: "",
        sort: "date-desc",
        page: 1,
      });
    });

    it("updates URL using pushState when filters change", async () => {
      wrapper = mount(GameFilter, {
        props: {
          platformsData: JSON.stringify(mockPlatforms),
        },
      });

      await wrapper.vm.$nextTick();

      const platformSelect = wrapper.find("#platform-filter");
      await platformSelect.setValue("PC");
      await platformSelect.trigger("change");

      // Verify pushState was called with correct URL
      expect(window.history.pushState).toHaveBeenCalledWith(
        {},
        "",
        expect.stringContaining("platform=PC"),
      );

      // Verify event was dispatched
      expect(window.dispatchEvent).toHaveBeenCalledWith(
        expect.objectContaining({
          type: "games-filter-changed",
          detail: {
            search: "",
            platform: "PC",
            sort: "title-asc",
            page: 1,
          },
        }),
      );
    });

    it("emits games-filter-changed event when clear button is clicked", async () => {
      // Set URL parameters first
      window.history.replaceState(
        {},
        "",
        "?search=test&platform=PC&sort=date-desc",
      );

      wrapper = mount(GameFilter, {
        props: {
          platformsData: JSON.stringify(mockPlatforms),
        },
      });

      await wrapper.vm.$nextTick();

      const clearButton = wrapper.find(".clear-filters-btn");
      await clearButton.trigger("click");

      // Verify CustomEvent was dispatched with empty filters
      expect(eventListenerSpy).toHaveBeenCalled();
      const event = eventListenerSpy.mock.calls[0][0];
      expect(event.type).toBe("games-filter-changed");
      expect(event.detail).toEqual({
        search: "",
        platform: "",
        sort: "title-asc",
        page: 1,
      });
    });
  });

  describe("Debounced Search", () => {
    it("applies filters after debounce delay when typing in search", async () => {
      wrapper = mount(GameFilter, {
        props: {
          platformsData: JSON.stringify(mockPlatforms),
        },
      });

      const searchInput = wrapper.find("#search-filter");

      // Type in the search input
      await searchInput.setValue("zelda");

      // Filter should NOT be applied immediately
      expect(wrapper.vm.searchQuery).toBe("");

      // Fast-forward timers by 800ms (the debounce delay)
      jest.advanceTimersByTime(800);
      await wrapper.vm.$nextTick();

      // Now the component state should be updated
      expect(wrapper.vm.searchQuery).toBe("zelda");
    });

    it("debounces multiple rapid inputs", async () => {
      wrapper = mount(GameFilter, {
        props: {
          platformsData: JSON.stringify(mockPlatforms),
        },
      });

      const searchInput = wrapper.find("#search-filter");

      // Simulate rapid typing
      await searchInput.setValue("z");
      jest.advanceTimersByTime(400);

      await searchInput.setValue("ze");
      jest.advanceTimersByTime(400);

      await searchInput.setValue("zel");

      // Still shouldn't have updated yet (debounce restarts on each input)
      expect(wrapper.vm.searchQuery).toBe("");

      // Complete the debounce delay
      jest.advanceTimersByTime(800);
      await wrapper.vm.$nextTick();

      // Now it should be updated with the final value
      expect(wrapper.vm.searchQuery).toBe("zel");
    });
  });

  describe("Edge Cases", () => {
    it("handles empty platforms array", () => {
      wrapper = mount(GameFilter, {
        props: {
          platformsData: JSON.stringify([]),
        },
      });

      const options = wrapper.find("#platform-filter").findAll("option");
      expect(options).toHaveLength(1); // Only "All Platforms"
    });

    it("handles invalid JSON in platformsData", () => {
      wrapper = mount(GameFilter, {
        props: {
          platformsData: "invalid-json",
        },
      });

      // Should render without crashing
      expect(wrapper.find(".game-filter").exists()).toBe(true);
      const options = wrapper.find("#platform-filter").findAll("option");
      expect(options).toHaveLength(1); // Only "All Platforms"
    });

    it("handles missing platformsData prop", async () => {
      // Suppress console.error and console.warn for this test
      const consoleSpy = jest
        .spyOn(console, "error")
        .mockImplementation(() => {});
      const warnSpy = jest.spyOn(console, "warn").mockImplementation(() => {});

      wrapper = mount(GameFilter, {
        props: {
          platformsData: "",
        },
      });

      await wrapper.vm.$nextTick();

      // Should render without crashing
      expect(wrapper.find(".game-filter").exists()).toBe(true);

      consoleSpy.mockRestore();
      warnSpy.mockRestore();
    });
  });
});
