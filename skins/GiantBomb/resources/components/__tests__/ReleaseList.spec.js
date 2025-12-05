const { mount } = require("@vue/test-utils");
const ReleaseList = require("../ReleaseList.vue");

// Mock the countryFlags module
jest.mock("../../helpers/countryFlags.js", () => ({
  getCountryCode: jest.fn((country) => {
    const codes = {
      "United States": "us",
      Japan: "jp",
      "United Kingdom": "gb",
    };
    return codes[country] || "";
  }),
  getFlagUrl: jest.fn((code, size) => {
    if (!code) return "";
    return `https://flagcdn.com/${size}/${code}.png`;
  }),
}));

describe("ReleaseList", () => {
  const mockWeekGroups = [
    {
      label: "November 2, 2025 - November 8, 2025",
      releases: [
        {
          title: "Hyrule Warriors: Age of Imprisonment",
          url: "/wiki/Hyrule_Warriors:_Age_of_Imprisonment",
          image:
            "https://www.giantbomb.com/a/uploads/scale_small/20/201266/3821180-5821929028-coag7.jpg",
          releaseDateFormatted: "November 6, 2025",
          region: "United States",
          platforms: [{ abbrev: "NSW2", title: "Nintendo Switch 2" }],
        },
        {
          title: "Arcade Archives: Tokyo Wars",
          url: "/wiki/Arcade_Archives:_Tokyo_Wars",
          image:
            "https://www.giantbomb.com/a/uploads/square_small/1/12985/1307350-tokyo_wars00.jpg",
          releaseDateFormatted: "November 6, 2025",
          region: "United Kingdom",
          platforms: [
            { abbrev: "NSW", title: "Nintendo Switch" },
            { abbrev: "PS4", title: "PlayStation 4" },
          ],
        },
      ],
    },
  ];

  const defaultProps = {
    initialData: JSON.stringify(mockWeekGroups),
  };

  beforeEach(() => {
    // Clear all mocks before each test
    jest.clearAllMocks();

    // Mock fetch
    global.fetch = jest.fn();

    // Mock window.location
    delete window.location;
    window.location = { pathname: "/wiki/New_Releases" };
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  describe("Initial Render", () => {
    it("renders week groups and releases", async () => {
      const wrapper = mount(ReleaseList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const weekGroups = wrapper.findAll(".week-group");
      expect(weekGroups).toHaveLength(1);

      const weekLabel = wrapper.find(".week-label");
      expect(weekLabel.text()).toBe("November 2, 2025 - November 8, 2025");

      const releaseCards = wrapper.findAll(".release-card");
      expect(releaseCards).toHaveLength(2);
    });

    it("displays release information correctly", async () => {
      const wrapper = mount(ReleaseList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const firstRelease = wrapper.findAll(".release-card")[0];
      const title = firstRelease.find(".release-title");
      expect(title.text()).toBe("Hyrule Warriors: Age of Imprisonment");

      const date = firstRelease.find(".release-date");
      expect(date.text()).toContain("November 6, 2025");

      const link = firstRelease.find(".release-card-link");
      expect(link.attributes("href")).toBe(
        "/wiki/Hyrule_Warriors:_Age_of_Imprisonment",
      );
    });

    it("displays release images", async () => {
      const wrapper = mount(ReleaseList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const images = wrapper.findAll(".release-image img");
      expect(images).toHaveLength(2);
      expect(images[0].attributes("src")).toBe(
        "https://www.giantbomb.com/a/uploads/scale_small/20/201266/3821180-5821929028-coag7.jpg",
      );
      expect(images[0].attributes("alt")).toBe(
        "Hyrule Warriors: Age of Imprisonment",
      );
    });

    it("displays placeholder image when no image is provided", async () => {
      const noImageData = [
        {
          label: "November 2, 2025 - November 8, 2025",
          releases: [
            {
              title: "Game Without Image",
              url: "/wiki/Game_Without_Image",
              image: null,
              releaseDateFormatted: "May 12, 2023",
              region: "United States",
              platforms: [],
            },
          ],
        },
      ];

      const wrapper = mount(ReleaseList, {
        props: {
          initialData: JSON.stringify(noImageData),
        },
      });

      await wrapper.vm.$nextTick();

      const placeholder = wrapper.find(".release-image-placeholder");
      expect(placeholder.exists()).toBe(true);

      const img = placeholder.find("img");
      expect(img.attributes("src")).toContain("gb_default-16_9.png");
    });

    it("displays platform badges", async () => {
      const wrapper = mount(ReleaseList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const platforms = wrapper.findAll(".platform-badge");
      expect(platforms).toHaveLength(3);
      expect(platforms[0].text()).toBe("NSW2");
      expect(platforms[0].attributes("title")).toBe("Nintendo Switch 2");
      expect(platforms[1].text()).toBe("NSW");
      expect(platforms[1].attributes("title")).toBe("Nintendo Switch");
      expect(platforms[2].text()).toBe("PS4");
      expect(platforms[2].attributes("title")).toBe("PlayStation 4");
    });

    it("displays region flags when country code is available", async () => {
      const wrapper = mount(ReleaseList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const flags = wrapper.findAll(".region-flag");
      expect(flags).toHaveLength(2);
      expect(flags[0].attributes("src")).toBe("https://flagcdn.com/w20/us.png");
      expect(flags[0].attributes("alt")).toBe("United States");
      expect(flags[1].attributes("src")).toBe("https://flagcdn.com/w20/gb.png");
      expect(flags[1].attributes("alt")).toBe("United Kingdom");
    });

    it("displays region text when country code is not available", async () => {
      const noFlagData = [
        {
          label: "November 2, 2025 - November 8, 2025",
          releases: [
            {
              title: "Test Game",
              url: "/wiki/Test_Game",
              image: "https://example.com/test.jpg",
              releaseDateFormatted: "May 12, 2023",
              region: "Unknown Region",
              platforms: [],
            },
          ],
        },
      ];

      const wrapper = mount(ReleaseList, {
        props: {
          initialData: JSON.stringify(noFlagData),
        },
      });

      await wrapper.vm.$nextTick();

      const regionText = wrapper.find(".region-text");
      expect(regionText.exists()).toBe(true);
      expect(regionText.text()).toContain("Unknown Region");
    });
  });

  describe("Loading State", () => {
    it("displays loading state when fetching releases", async () => {
      const wrapper = mount(ReleaseList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Set loading to true
      wrapper.vm.loading = true;
      await wrapper.vm.$nextTick();

      const loadingDiv = wrapper.find(".item-loading");
      expect(loadingDiv.exists()).toBe(true);

      const spinner = wrapper.find(".loading-spinner");
      expect(spinner.exists()).toBe(true);

      expect(loadingDiv.text()).toContain("Loading new releases...");
    });

    it("hides release content when loading", async () => {
      const wrapper = mount(ReleaseList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Set loading to true
      wrapper.vm.loading = true;
      await wrapper.vm.$nextTick();

      const weekGroups = wrapper.findAll(".week-group");
      expect(weekGroups).toHaveLength(0);
    });
  });

  describe("Empty State", () => {
    it("displays no releases message when week groups are empty", async () => {
      const wrapper = mount(ReleaseList, {
        props: {
          initialData: "[]",
        },
      });

      await wrapper.vm.$nextTick();

      const noReleases = wrapper.find(".empty-state");
      expect(noReleases.exists()).toBe(true);
      expect(noReleases.text()).toContain(
        "No releases found for the selected filters.",
      );
    });
  });

  describe("Filter Change Handling", () => {
    it("listens for filter change events", async () => {
      global.fetch = jest.fn(() =>
        Promise.resolve({
          ok: true,
          json: () =>
            Promise.resolve({
              success: true,
              weekGroups: [
                {
                  label: "Filtered Week",
                  releases: [],
                },
              ],
            }),
        }),
      );

      const wrapper = mount(ReleaseList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Dispatch filter change event
      const event = new CustomEvent("releases-filter-changed", {
        detail: {
          region: "United States",
          platform: "PlayStation 5",
        },
      });
      window.dispatchEvent(event);

      // Wait for async fetch to complete
      await new Promise((resolve) => setTimeout(resolve, 0));
      await wrapper.vm.$nextTick();

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining("region=United+States"),
        expect.any(Object),
      );
      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining("platform=PlayStation+5"),
        expect.any(Object),
      );
    });

    it("updates week groups after successful fetch", async () => {
      const newWeekGroups = [
        {
          label: "November 9, 2025 - November 15, 2025",
          releases: [
            {
              title: "New Game",
              url: "/wiki/New_Game",
              image: "https://example.com/new.jpg",
              releaseDateFormatted: "November 12, 2025",
              region: "United States",
              platforms: [],
            },
          ],
        },
      ];

      global.fetch = jest.fn(() =>
        Promise.resolve({
          ok: true,
          json: () =>
            Promise.resolve({
              success: true,
              weekGroups: newWeekGroups,
            }),
        }),
      );

      const wrapper = mount(ReleaseList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const event = new CustomEvent("releases-filter-changed", {
        detail: { region: "Japan", platform: "" },
      });
      window.dispatchEvent(event);

      await new Promise((resolve) => setTimeout(resolve, 0));
      await wrapper.vm.$nextTick();

      const weekLabel = wrapper.find(".week-label");
      expect(weekLabel.text()).toBe("November 9, 2025 - November 15, 2025");
    });

    it("handles fetch errors gracefully", async () => {
      const consoleErrorSpy = jest.spyOn(console, "error").mockImplementation();

      global.fetch = jest.fn(() =>
        Promise.resolve({
          ok: false,
          status: 500,
          text: () => Promise.resolve("Server error"),
        }),
      );

      const wrapper = mount(ReleaseList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const event = new CustomEvent("releases-filter-changed", {
        detail: { region: "", platform: "" },
      });
      window.dispatchEvent(event);

      await new Promise((resolve) => setTimeout(resolve, 0));
      await wrapper.vm.$nextTick();

      expect(consoleErrorSpy).toHaveBeenCalled();

      // Should keep existing data on error
      const weekGroups = wrapper.findAll(".week-group");
      expect(weekGroups).toHaveLength(1);

      consoleErrorSpy.mockRestore();
    });

    it("handles API error responses", async () => {
      const consoleErrorSpy = jest.spyOn(console, "error").mockImplementation();

      global.fetch = jest.fn(() =>
        Promise.resolve({
          ok: true,
          json: () =>
            Promise.resolve({
              success: false,
              error: "Invalid parameters",
            }),
        }),
      );

      const wrapper = mount(ReleaseList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const event = new CustomEvent("releases-filter-changed", {
        detail: { region: "", platform: "" },
      });
      window.dispatchEvent(event);

      await new Promise((resolve) => setTimeout(resolve, 0));
      await wrapper.vm.$nextTick();

      expect(consoleErrorSpy).toHaveBeenCalledWith(
        "API returned error:",
        expect.any(Object),
      );

      consoleErrorSpy.mockRestore();
    });
  });

  describe("HTML Entity Decoding", () => {
    it("decodes HTML entities in initial data", async () => {
      const encodedData = [
        {
          label: "November 2, 2025 - November 8, 2025",
          releases: [
            {
              title: "Game &amp; Test",
              url: "/wiki/Game_%26_Test",
              image: "https://example.com/test.jpg",
              releaseDateFormatted: "May 12, 2023",
              region: "United States",
              platforms: [],
            },
          ],
        },
      ];

      const wrapper = mount(ReleaseList, {
        props: {
          initialData: JSON.stringify(encodedData),
        },
      });

      await wrapper.vm.$nextTick();

      const title = wrapper.find(".release-title");
      expect(title.text()).toBe("Game & Test");
    });

    it("handles invalid JSON gracefully", async () => {
      const consoleErrorSpy = jest.spyOn(console, "error").mockImplementation();

      const wrapper = mount(ReleaseList, {
        props: {
          initialData: "invalid json",
        },
      });

      await wrapper.vm.$nextTick();

      expect(consoleErrorSpy).toHaveBeenCalledWith(
        "Failed to parse initial data:",
        expect.any(Error),
      );

      const noReleases = wrapper.find(".empty-state");
      expect(noReleases.exists()).toBe(true);

      consoleErrorSpy.mockRestore();
    });
  });

  describe("Event Cleanup", () => {
    it("removes event listener on unmount", async () => {
      const removeEventListenerSpy = jest.spyOn(window, "removeEventListener");

      const wrapper = mount(ReleaseList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      wrapper.unmount();

      expect(removeEventListenerSpy).toHaveBeenCalledWith(
        "releases-filter-changed",
        expect.any(Function),
      );

      removeEventListenerSpy.mockRestore();
    });
  });
});
