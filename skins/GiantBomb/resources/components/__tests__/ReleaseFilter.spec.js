const { mount } = require("@vue/test-utils");
const ReleaseFilter = require("../ReleaseFilter.vue");

describe("ReleaseFilter", () => {
  const mockPlatforms = [
    { name: "PlayStation 5", displayName: "PlayStation 5" },
    { name: "Xbox Series X|S", displayName: "Xbox Series X|S" },
    { name: "Nintendo Switch", displayName: "Nintendo Switch" },
  ];

  const defaultProps = {
    platformsData: JSON.stringify(mockPlatforms),
  };

  beforeEach(() => {
    // Mock window.history to update window.location
    window.history.pushState = jest.fn((state, title, url) => {
      // Update window.location to reflect the new URL
      if (url) {
        const fullUrl = url.startsWith("http")
          ? url
          : `http://localhost:8080${url}`;
        const urlObj = new URL(fullUrl);
        window.location.href = fullUrl;
        window.location.pathname = urlObj.pathname;
        window.location.search = urlObj.search;
      }
    });

    // Mock URLSearchParams
    global.URLSearchParams = jest.fn().mockImplementation((search) => {
      const params = new Map();
      if (search) {
        search
          .replace("?", "")
          .split("&")
          .forEach((pair) => {
            const [key, value] = pair.split("=");
            if (key && value) {
              params.set(key, decodeURIComponent(value));
            }
          });
      }
      return {
        get: (key) => params.get(key) || null,
        set: (key, value) => params.set(key, value),
        delete: (key) => params.delete(key),
        toString: () => {
          const pairs = [];
          params.forEach((value, key) => {
            pairs.push(`${key}=${encodeURIComponent(value)}`);
          });
          return pairs.join("&");
        },
      };
    });

    // Clear all event listeners
    window.dispatchEvent = jest.fn();
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  describe("Initial Render", () => {
    it("renders filter title and labels", async () => {
      const wrapper = mount(ReleaseFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const title = wrapper.find(".filter-title");
      expect(title.exists()).toBe(true);
      expect(title.text()).toBe("Filter");

      const labels = wrapper.findAll(".filter-label");
      expect(labels).toHaveLength(2);
      expect(labels[0].text()).toBe("Region");
      expect(labels[1].text()).toBe("Platform");
    });

    it("renders region select with options", async () => {
      const wrapper = mount(ReleaseFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const regionSelect = wrapper.find("#region-filter");
      expect(regionSelect.exists()).toBe(true);

      const options = regionSelect.findAll("option");
      expect(options).toHaveLength(5); // "All Regions" + 4 regions
      expect(options[0].text()).toBe("All Regions");
      expect(options[1].text()).toBe("United States");
      expect(options[2].text()).toBe("United Kingdom");
      expect(options[3].text()).toBe("Japan");
      expect(options[4].text()).toBe("Australia");
    });

    it("renders platform select with parsed platforms", async () => {
      const wrapper = mount(ReleaseFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const platformSelect = wrapper.find("#platform-filter");
      expect(platformSelect.exists()).toBe(true);

      const options = platformSelect.findAll("option");
      expect(options).toHaveLength(4); // "All Platforms" + 3 platforms
      expect(options[0].text()).toBe("All Platforms");
      expect(options[1].text()).toBe("PlayStation 5");
      expect(options[2].text()).toBe("Xbox Series X|S");
      expect(options[3].text()).toBe("Nintendo Switch");
    });

    it("does not show clear filters button when no filters are active", async () => {
      const wrapper = mount(ReleaseFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const clearButton = wrapper.find(".clear-filters-btn");
      expect(clearButton.exists()).toBe(false);
    });
  });

  describe("Filter Selection", () => {
    it("updates URL and dispatches event when region is selected", async () => {
      const wrapper = mount(ReleaseFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const regionSelect = wrapper.find("#region-filter");
      await regionSelect.setValue("United States");

      expect(window.history.pushState).toHaveBeenCalledWith(
        {},
        "",
        expect.stringContaining("region=United%20States"),
      );

      expect(window.dispatchEvent).toHaveBeenCalledWith(
        expect.objectContaining({
          type: "releases-filter-changed",
          detail: {
            region: "United States",
            platform: "",
          },
        }),
      );
    });

    it("updates URL and dispatches event when platform is selected", async () => {
      const wrapper = mount(ReleaseFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const platformSelect = wrapper.find("#platform-filter");
      await platformSelect.setValue("PlayStation 5");

      expect(window.history.pushState).toHaveBeenCalledWith(
        {},
        "",
        expect.stringContaining("platform=PlayStation%205"),
      );

      expect(window.dispatchEvent).toHaveBeenCalledWith(
        expect.objectContaining({
          type: "releases-filter-changed",
          detail: {
            region: "",
            platform: "PlayStation 5",
          },
        }),
      );
    });

    it("updates URL with both filters when both are selected", async () => {
      const wrapper = mount(ReleaseFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      await wrapper.find("#region-filter").setValue("Japan");
      await wrapper.find("#platform-filter").setValue("Nintendo Switch");

      const lastCall =
        window.history.pushState.mock.calls[
          window.history.pushState.mock.calls.length - 1
        ];
      expect(lastCall[2]).toContain("region=Japan");
      expect(lastCall[2]).toContain("platform=Nintendo%20Switch");
    });

    it("shows clear filters button when filters are active", async () => {
      const wrapper = mount(ReleaseFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      await wrapper.find("#region-filter").setValue("United States");
      await wrapper.vm.$nextTick();

      const clearButton = wrapper.find(".clear-filters-btn");
      expect(clearButton.exists()).toBe(true);
      expect(clearButton.text()).toBe("Clear Filters");
    });
  });

  describe("Clear Filters", () => {
    it("clears all filters when clear button is clicked", async () => {
      const wrapper = mount(ReleaseFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Set some filters
      await wrapper.find("#region-filter").setValue("United States");
      await wrapper.find("#platform-filter").setValue("PlayStation 5");
      await wrapper.vm.$nextTick();

      // Click clear button
      const clearButton = wrapper.find(".clear-filters-btn");
      await clearButton.trigger("click");

      expect(wrapper.vm.selectedRegion).toBe("");
      expect(wrapper.vm.selectedPlatform).toBe("");

      const regionSelect = wrapper.find("#region-filter");
      const platformSelect = wrapper.find("#platform-filter");
      expect(regionSelect.element.value).toBe("");
      expect(platformSelect.element.value).toBe("");
    });

    it("updates URL to remove parameters when cleared", async () => {
      const wrapper = mount(ReleaseFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      await wrapper.find("#region-filter").setValue("Japan");
      await wrapper.vm.$nextTick();

      const clearButton = wrapper.find(".clear-filters-btn");
      await clearButton.trigger("click");

      await wrapper.vm.$nextTick();

      // Check the last call to pushState (after clear is clicked)
      const lastCall =
        window.history.pushState.mock.calls[
          window.history.pushState.mock.calls.length - 1
        ];
      expect(lastCall[2]).toBe("/");
    });

    it("dispatches event with empty filters when cleared", async () => {
      const wrapper = mount(ReleaseFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      await wrapper.find("#region-filter").setValue("United States");
      await wrapper.vm.$nextTick();

      // Clear dispatch mock
      window.dispatchEvent.mockClear();

      const clearButton = wrapper.find(".clear-filters-btn");
      await clearButton.trigger("click");

      expect(window.dispatchEvent).toHaveBeenCalledWith(
        expect.objectContaining({
          type: "releases-filter-changed",
          detail: {
            region: "",
            platform: "",
          },
        }),
      );
    });

    it("hides clear button after clearing", async () => {
      const wrapper = mount(ReleaseFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      await wrapper.find("#region-filter").setValue("United States");
      await wrapper.vm.$nextTick();

      const clearButton = wrapper.find(".clear-filters-btn");
      await clearButton.trigger("click");
      await wrapper.vm.$nextTick();

      expect(wrapper.find(".clear-filters-btn").exists()).toBe(false);
    });
  });

  describe("HTML Entity Decoding", () => {
    it("decodes HTML entities in platforms data", async () => {
      const encodedPlatforms = [
        { name: "Xbox Series X|S", displayName: "Xbox Series X&amp;S" },
      ];

      const wrapper = mount(ReleaseFilter, {
        props: {
          platformsData: JSON.stringify(encodedPlatforms),
        },
      });

      await wrapper.vm.$nextTick();

      const platformSelect = wrapper.find("#platform-filter");
      const options = platformSelect.findAll("option");

      // Should decode the &amp; back to &
      expect(options[1].text()).toContain("Xbox Series X&S");
    });

    it("handles invalid JSON gracefully", async () => {
      const consoleErrorSpy = jest.spyOn(console, "error").mockImplementation();

      const wrapper = mount(ReleaseFilter, {
        props: {
          platformsData: "invalid json",
        },
      });

      await wrapper.vm.$nextTick();

      expect(consoleErrorSpy).toHaveBeenCalledWith(
        "Failed to parse platforms data:",
        expect.any(Error),
      );

      expect(wrapper.vm.platforms).toEqual([]);

      const platformSelect = wrapper.find("#platform-filter");
      const options = platformSelect.findAll("option");
      expect(options).toHaveLength(1); // Only "All Platforms"

      consoleErrorSpy.mockRestore();
    });
  });

  describe("Computed Properties", () => {
    it("hasActiveFilters returns true when region is selected", async () => {
      const wrapper = mount(ReleaseFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      expect(wrapper.vm.hasActiveFilters).toBe(false);

      await wrapper.find("#region-filter").setValue("Japan");
      expect(wrapper.vm.hasActiveFilters).toBe(true);
    });

    it("hasActiveFilters returns true when platform is selected", async () => {
      const wrapper = mount(ReleaseFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      await wrapper.find("#platform-filter").setValue("PlayStation 5");
      expect(wrapper.vm.hasActiveFilters).toBe(true);
    });

    it("hasActiveFilters returns false when all filters are cleared", async () => {
      const wrapper = mount(ReleaseFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      await wrapper.find("#region-filter").setValue("Japan");
      expect(wrapper.vm.hasActiveFilters).toBe(true);

      await wrapper.find("#region-filter").setValue("");
      expect(wrapper.vm.hasActiveFilters).toBe(false);
    });
  });

  describe("Edge Cases", () => {
    it("handles empty platforms array", async () => {
      const wrapper = mount(ReleaseFilter, {
        props: {
          platformsData: "[]",
        },
      });

      await wrapper.vm.$nextTick();

      const platformSelect = wrapper.find("#platform-filter");
      const options = platformSelect.findAll("option");
      expect(options).toHaveLength(1); // Only "All Platforms"
    });

    it("handles URL with no parameters", async () => {
      window.location.search = "";

      const wrapper = mount(ReleaseFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      expect(wrapper.vm.selectedRegion).toBe("");
      expect(wrapper.vm.selectedPlatform).toBe("");
      expect(wrapper.vm.hasActiveFilters).toBe(false);
    });

    it("handles deselecting a filter by choosing 'All'", async () => {
      const wrapper = mount(ReleaseFilter, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      await wrapper.find("#region-filter").setValue("Japan");
      expect(wrapper.vm.hasActiveFilters).toBe(true);

      await wrapper.find("#region-filter").setValue("");
      expect(wrapper.vm.hasActiveFilters).toBe(false);
      expect(wrapper.find(".clear-filters-btn").exists()).toBe(false);
    });
  });
});
