const { mount } = require("@vue/test-utils");
const PlatformList = require("../PlatformList.vue");

describe("PlatformList", () => {
  const mockPlatforms = [
    {
      title: "PlayStation 5",
      shortName: "PS5",
      url: "/wiki/PlayStation_5",
      image:
        "https://www.giantbomb.com/a/uploads/scale_small/0/3699/2970349-ps5.jpg",
      deck: "Sony's next-generation console",
      releaseDateTimestamp: 1605099600,
      releaseDateFormatted: "November 12, 2020",
      dateSpecificity: "full",
      gameCount: 450,
    },
    {
      title: "Xbox Series X",
      shortName: "XSX",
      url: "/wiki/Xbox_Series_X",
      image:
        "https://www.giantbomb.com/a/uploads/scale_small/0/3699/2970350-xsx.jpg",
      deck: "Microsoft's flagship console",
      releaseDateTimestamp: 1605014400,
      releaseDateFormatted: "November 10, 2020",
      dateSpecificity: "full",
      gameCount: 380,
    },
  ];

  const defaultProps = {
    initialData: JSON.stringify(mockPlatforms),
    totalCount: "2",
    currentPage: "1",
    totalPages: "1",
  };

  let consoleErrorSpy;
  const originalConsoleError = console.error;

  beforeEach(() => {
    // Clear all mocks before each test
    jest.clearAllMocks();

    // Suppress expected "Failed to fetch platforms" and jsdom navigation errors
    // These occur in tests that don't mock fetch or navigation
    consoleErrorSpy = jest
      .spyOn(console, "error")
      .mockImplementation((message, ...args) => {
        // Suppress jsdom navigation errors
        if (
          typeof message === "object" &&
          message?.message?.includes("Not implemented: navigation")
        ) {
          return;
        }
        // Suppress "Failed to fetch platforms" errors
        if (
          typeof message === "string" &&
          message.includes("Failed to fetch platforms")
        ) {
          return; // Suppress this specific error
        }
        // Allow other console.error calls through
        originalConsoleError(message, ...args);
      });

    // Mock fetch
    global.fetch = jest.fn();

    // Mock window.location
    delete window.location;
    window.location = {
      pathname: "/wiki/Platforms",
      href: "http://localhost:8080/wiki/Platforms",
      origin: "http://localhost:8080",
      search: "",
    };

    // Mock window.scrollTo
    window.scrollTo = jest.fn();

    // Mock window.history
    window.history.pushState = jest.fn();
  });

  afterEach(() => {
    // Restore console.error
    if (consoleErrorSpy) {
      consoleErrorSpy.mockRestore();
    }
    jest.restoreAllMocks();
  });

  describe("Initial Render", () => {
    it("renders platforms grid", async () => {
      const wrapper = mount(PlatformList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const platformsGrid = wrapper.find(".platforms-grid");
      expect(platformsGrid.exists()).toBe(true);

      const platformCards = wrapper.findAll(".platform-card");
      expect(platformCards).toHaveLength(2);
    });

    it("displays platform information correctly", async () => {
      const wrapper = mount(PlatformList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const firstPlatform = wrapper.findAll(".platform-card")[0];
      const title = firstPlatform.find(".platform-title");
      expect(title.text()).toBe("PlayStation 5");

      const deck = firstPlatform.find(".platform-deck");
      expect(deck.text()).toBe("Sony's next-generation console");

      const date = firstPlatform.find(".platform-date");
      expect(date.text()).toContain("November 12, 2020");

      const gameCount = firstPlatform.find(".platform-game-count");
      expect(gameCount.text()).toBe("Games: 450");

      const link = firstPlatform.find(".platform-card-link");
      expect(link.attributes("href")).toBe("/wiki/PlayStation_5");
    });

    it("displays platform images", async () => {
      const wrapper = mount(PlatformList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const images = wrapper.findAll(".platform-image img");
      expect(images).toHaveLength(2);
      expect(images[0].attributes("src")).toBe(
        "https://www.giantbomb.com/a/uploads/scale_small/0/3699/2970349-ps5.jpg",
      );
      expect(images[0].attributes("alt")).toBe("PlayStation 5");
    });

    it("displays placeholder image when no image is provided", async () => {
      const noImageData = [
        {
          title: "Platform Without Image",
          url: "/wiki/Platform_Without_Image",
          image: null,
          deck: "A platform without an image",
          releaseDateFormatted: "January 1, 2000",
          gameCount: 10,
        },
      ];

      const wrapper = mount(PlatformList, {
        props: {
          initialData: JSON.stringify(noImageData),
          totalCount: "1",
          currentPage: "1",
          totalPages: "1",
        },
      });

      await wrapper.vm.$nextTick();

      const placeholder = wrapper.find(".platform-image-placeholder");
      expect(placeholder.exists()).toBe(true);

      const img = placeholder.find("img");
      expect(img.attributes("src")).toContain("gb_default-16_9.png");
    });

    it("displays deck when provided", async () => {
      const wrapper = mount(PlatformList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const decks = wrapper.findAll(".platform-deck");
      expect(decks).toHaveLength(2);
      expect(decks[0].text()).toBe("Sony's next-generation console");
    });

    it("does not display deck when not provided", async () => {
      const noDeckData = [
        {
          title: "Platform Without Deck",
          url: "/wiki/Platform_Without_Deck",
          image: "https://example.com/test.jpg",
          deck: null,
          releaseDateFormatted: "January 1, 2000",
          gameCount: 10,
        },
      ];

      const wrapper = mount(PlatformList, {
        props: {
          initialData: JSON.stringify(noDeckData),
          totalCount: "1",
          currentPage: "1",
          totalPages: "1",
        },
      });

      await wrapper.vm.$nextTick();

      const deck = wrapper.find(".platform-deck");
      expect(deck.exists()).toBe(false);
    });

    it("does not display game count when not provided", async () => {
      const noGameCountData = [
        {
          title: "Platform Without Game Count",
          url: "/wiki/Platform_Without_Game_Count",
          image: "https://example.com/test.jpg",
          deck: "A platform",
          releaseDateFormatted: "January 1, 2000",
          gameCount: null,
        },
      ];

      const wrapper = mount(PlatformList, {
        props: {
          initialData: JSON.stringify(noGameCountData),
          totalCount: "1",
          currentPage: "1",
          totalPages: "1",
        },
      });

      await wrapper.vm.$nextTick();

      const gameCount = wrapper.find(".platform-game-count");
      expect(gameCount.exists()).toBe(false);
    });
  });

  describe("Loading State", () => {
    it("displays loading state when fetching platforms", async () => {
      const wrapper = mount(PlatformList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Set loading to true
      wrapper.vm.loading = true;
      await wrapper.vm.$nextTick();

      const loadingDiv = wrapper.find(".platforms-loading");
      expect(loadingDiv.exists()).toBe(true);

      const spinner = wrapper.find(".loading-spinner");
      expect(spinner.exists()).toBe(true);

      expect(loadingDiv.text()).toContain("Loading platforms...");
    });

    it("hides platform content when loading", async () => {
      const wrapper = mount(PlatformList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Set loading to true
      wrapper.vm.loading = true;
      await wrapper.vm.$nextTick();

      const platformsGrid = wrapper.find(".platforms-grid");
      expect(platformsGrid.exists()).toBe(false);
    });
  });

  describe("Empty State", () => {
    it("displays no platforms message when platforms array is empty", async () => {
      const wrapper = mount(PlatformList, {
        props: {
          initialData: "[]",
          totalCount: "0",
          currentPage: "1",
          totalPages: "1",
        },
      });

      await wrapper.vm.$nextTick();

      const noPlatforms = wrapper.find(".no-platforms");
      expect(noPlatforms.exists()).toBe(true);
      expect(noPlatforms.text()).toContain(
        "No platforms found for the selected filters.",
      );
    });
  });

  describe("Pagination", () => {
    it("displays pagination when total pages is greater than 1", async () => {
      const wrapper = mount(PlatformList, {
        props: {
          initialData: JSON.stringify(mockPlatforms),
          totalCount: "50",
          currentPage: "2",
          totalPages: "5",
        },
      });

      await wrapper.vm.$nextTick();

      const pagination = wrapper.find(".pagination");
      expect(pagination.exists()).toBe(true);

      const paginationInfo = wrapper.find(".pagination-info");
      expect(paginationInfo.text()).toContain("Page 2 of 5");
      expect(paginationInfo.text()).toContain("(50 total)");
    });

    it("hides pagination when total pages is 1", async () => {
      const wrapper = mount(PlatformList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const pagination = wrapper.find(".pagination");
      expect(pagination.exists()).toBe(false);
    });

    it("disables previous button on first page", async () => {
      const wrapper = mount(PlatformList, {
        props: {
          initialData: JSON.stringify(mockPlatforms),
          totalCount: "50",
          currentPage: "1",
          totalPages: "5",
        },
      });

      await wrapper.vm.$nextTick();

      const prevButton = wrapper.findAll(".pagination-btn")[0];
      expect(prevButton.attributes("disabled")).toBeDefined();
    });

    it("disables next button on last page", async () => {
      const wrapper = mount(PlatformList, {
        props: {
          initialData: JSON.stringify(mockPlatforms),
          totalCount: "50",
          currentPage: "5",
          totalPages: "5",
        },
      });

      await wrapper.vm.$nextTick();

      const nextButton = wrapper.findAll(".pagination-btn")[1];
      expect(nextButton.attributes("disabled")).toBeDefined();
    });

    it("fetches platforms when next page is clicked", async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          success: true,
          platforms: [
            {
              title: "Nintendo Switch",
              url: "/wiki/Nintendo_Switch",
              image: "https://example.com/switch.jpg",
              deck: "Nintendo's hybrid console",
              releaseDateFormatted: "March 3, 2017",
              gameCount: 800,
            },
          ],
          totalCount: 50,
          currentPage: 2,
          totalPages: 5,
        }),
      });

      const wrapper = mount(PlatformList, {
        props: {
          initialData: JSON.stringify(mockPlatforms),
          totalCount: "50",
          currentPage: "1",
          totalPages: "5",
        },
      });

      await wrapper.vm.$nextTick();

      const nextButton = wrapper.findAll(".pagination-btn")[1];
      await nextButton.trigger("click");

      await new Promise((resolve) => setTimeout(resolve, 0));
      await wrapper.vm.$nextTick();

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining("action=get-platforms"),
        expect.any(Object),
      );
      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining("page=2"),
        expect.any(Object),
      );
    });

    it("updates URL when page changes", async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          success: true,
          platforms: mockPlatforms,
          totalCount: 50,
          currentPage: 3,
          totalPages: 5,
        }),
      });

      const wrapper = mount(PlatformList, {
        props: {
          initialData: JSON.stringify(mockPlatforms),
          totalCount: "50",
          currentPage: "2",
          totalPages: "5",
        },
      });

      await wrapper.vm.$nextTick();

      const nextButton = wrapper.findAll(".pagination-btn")[1];
      await nextButton.trigger("click");

      await new Promise((resolve) => setTimeout(resolve, 0));
      await wrapper.vm.$nextTick();

      expect(window.history.pushState).toHaveBeenCalledWith(
        {},
        "",
        expect.stringContaining("page=3"),
      );
    });

    it("scrolls to top when page changes", async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          success: true,
          platforms: mockPlatforms,
          totalCount: 50,
          currentPage: 2,
          totalPages: 5,
        }),
      });

      const wrapper = mount(PlatformList, {
        props: {
          initialData: JSON.stringify(mockPlatforms),
          totalCount: "50",
          currentPage: "1",
          totalPages: "5",
        },
      });

      await wrapper.vm.$nextTick();

      const nextButton = wrapper.findAll(".pagination-btn")[1];
      await nextButton.trigger("click");

      await new Promise((resolve) => setTimeout(resolve, 0));
      await wrapper.vm.$nextTick();

      expect(window.scrollTo).toHaveBeenCalledWith({
        top: 0,
        behavior: "smooth",
      });
    });

    it("does not navigate when clicking previous on first page", async () => {
      const wrapper = mount(PlatformList, {
        props: {
          initialData: JSON.stringify(mockPlatforms),
          totalCount: "50",
          currentPage: "1",
          totalPages: "5",
        },
      });

      await wrapper.vm.$nextTick();

      wrapper.vm.goToPage(0);
      await wrapper.vm.$nextTick();

      expect(global.fetch).not.toHaveBeenCalled();
    });

    it("does not navigate when clicking next on last page", async () => {
      const wrapper = mount(PlatformList, {
        props: {
          initialData: JSON.stringify(mockPlatforms),
          totalCount: "50",
          currentPage: "5",
          totalPages: "5",
        },
      });

      await wrapper.vm.$nextTick();

      wrapper.vm.goToPage(6);
      await wrapper.vm.$nextTick();

      expect(global.fetch).not.toHaveBeenCalled();
    });
  });

  describe("Filter Change Handling", () => {
    it("listens for filter change events", async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          success: true,
          platforms: [
            {
              title: "Filtered Platform",
              url: "/wiki/Filtered_Platform",
              image: "https://example.com/filtered.jpg",
              deck: "A filtered platform",
              releaseDateFormatted: "January 1, 2020",
              gameCount: 100,
            },
          ],
          totalCount: 1,
          currentPage: 1,
          totalPages: 1,
        }),
      });

      const wrapper = mount(PlatformList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Dispatch filter change event
      const event = new CustomEvent("platforms-filter-changed", {
        detail: {
          letter: "A",
          sort: "alphabetical",
          game_title: [],
          require_all_games: false,
          page: 1,
        },
      });
      window.dispatchEvent(event);

      // Wait for async fetch to complete
      await new Promise((resolve) => setTimeout(resolve, 0));
      await wrapper.vm.$nextTick();

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining("letter=A"),
        expect.any(Object),
      );
      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining("sort=alphabetical"),
        expect.any(Object),
      );
    });

    it("updates platforms after successful fetch", async () => {
      const newPlatforms = [
        {
          title: "New Platform",
          url: "/wiki/New_Platform",
          image: "https://example.com/new.jpg",
          deck: "A new platform",
          releaseDateFormatted: "December 1, 2024",
          gameCount: 50,
        },
      ];

      const wrapper = mount(PlatformList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Ensure any pending async operations from previous tests are done
      await new Promise((resolve) => setTimeout(resolve, 50));

      // Clear and reset the fetch mock completely
      global.fetch.mockClear();
      global.fetch.mockReset();

      // Set up the mock to resolve with new data
      global.fetch.mockImplementation(() =>
        Promise.resolve({
          ok: true,
          json: async () => ({
            success: true,
            platforms: newPlatforms,
            totalCount: 1,
            currentPage: 1,
            totalPages: 1,
          }),
        }),
      );

      const event = new CustomEvent("platforms-filter-changed", {
        detail: {
          letter: "N",
          sort: "release_date",
          game_title: [],
          require_all_games: false,
          page: 1,
        },
      });
      window.dispatchEvent(event);

      // Wait for loading to start
      await wrapper.vm.$nextTick();

      // Wait for loading to complete (poll until loading is false)
      let attempts = 0;
      while (wrapper.vm.loading && attempts < 30) {
        await new Promise((resolve) => setTimeout(resolve, 10));
        await wrapper.vm.$nextTick();
        attempts++;
      }

      // Verify fetch was called with correct parameters
      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining("action=get-platforms"),
        expect.any(Object),
      );
      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining("letter=N"),
        expect.any(Object),
      );

      // Verify loading completed
      expect(wrapper.vm.loading).toBe(false);

      // Additional wait for Vue to update the DOM with the new reactive data
      await wrapper.vm.$nextTick();
      await wrapper.vm.$nextTick();

      // Give extra time for DOM to settle
      await new Promise((resolve) => setTimeout(resolve, 10));
      await wrapper.vm.$nextTick();

      // Verify the component's internal state was updated
      expect(wrapper.vm.platforms).toHaveLength(1);
      expect(wrapper.vm.platforms[0].title).toBe("New Platform");

      // Verify the DOM was updated
      const platformCards = wrapper.findAll(".platform-card");
      expect(platformCards).toHaveLength(1);
      expect(platformCards[0].text()).toContain("New Platform");
    });

    it("handles game title filters", async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          success: true,
          platforms: mockPlatforms,
          totalCount: 2,
          currentPage: 1,
          totalPages: 1,
        }),
      });

      const wrapper = mount(PlatformList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const event = new CustomEvent("platforms-filter-changed", {
        detail: {
          letter: "",
          sort: "release_date",
          game_title: ["Games/Test_Game", "Games/Another_Game"],
          require_all_games: true,
          page: 1,
        },
      });
      window.dispatchEvent(event);

      await new Promise((resolve) => setTimeout(resolve, 0));
      await wrapper.vm.$nextTick();

      const fetchCall = global.fetch.mock.calls[0][0];
      expect(fetchCall).toContain("game_title[]=Games%2FTest_Game");
      expect(fetchCall).toContain("game_title[]=Games%2FAnother_Game");
      expect(fetchCall).toContain("require_all_games=1");
    });

    it("handles fetch errors gracefully", async () => {
      const consoleErrorSpy = jest.spyOn(console, "error").mockImplementation();

      global.fetch.mockResolvedValueOnce({
        ok: false,
        status: 500,
        text: async () => "Server error",
      });

      const wrapper = mount(PlatformList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const event = new CustomEvent("platforms-filter-changed", {
        detail: { letter: "", sort: "release_date", game_title: [], page: 1 },
      });
      window.dispatchEvent(event);

      await new Promise((resolve) => setTimeout(resolve, 0));
      await wrapper.vm.$nextTick();

      expect(consoleErrorSpy).toHaveBeenCalled();

      // Should keep existing data on error
      const platformCards = wrapper.findAll(".platform-card");
      expect(platformCards).toHaveLength(2);

      consoleErrorSpy.mockRestore();
    });

    it("handles API error responses", async () => {
      const consoleErrorSpy = jest.spyOn(console, "error").mockImplementation();

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          success: false,
          error: "Invalid parameters",
        }),
      });

      const wrapper = mount(PlatformList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const event = new CustomEvent("platforms-filter-changed", {
        detail: { letter: "", sort: "release_date", game_title: [], page: 1 },
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
          title: "Platform &amp; Test",
          url: "/wiki/Platform_%26_Test",
          image: "https://example.com/test.jpg",
          deck: "A platform &amp; test",
          releaseDateFormatted: "January 1, 2020",
          gameCount: 10,
        },
      ];

      const wrapper = mount(PlatformList, {
        props: {
          initialData: JSON.stringify(encodedData),
          totalCount: "1",
          currentPage: "1",
          totalPages: "1",
        },
      });

      await wrapper.vm.$nextTick();

      const title = wrapper.find(".platform-title");
      expect(title.text()).toBe("Platform & Test");

      const deck = wrapper.find(".platform-deck");
      expect(deck.text()).toBe("A platform & test");
    });

    it("handles invalid JSON gracefully", async () => {
      const consoleErrorSpy = jest.spyOn(console, "error").mockImplementation();

      const wrapper = mount(PlatformList, {
        props: {
          initialData: "invalid json",
          totalCount: "0",
          currentPage: "1",
          totalPages: "1",
        },
      });

      await wrapper.vm.$nextTick();

      expect(consoleErrorSpy).toHaveBeenCalledWith(
        "Failed to parse initial data:",
        expect.any(Error),
      );

      const noPlatforms = wrapper.find(".no-platforms");
      expect(noPlatforms.exists()).toBe(true);

      consoleErrorSpy.mockRestore();
    });
  });

  describe("Event Cleanup", () => {
    it("removes event listener on unmount", async () => {
      const removeEventListenerSpy = jest.spyOn(window, "removeEventListener");

      const wrapper = mount(PlatformList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      wrapper.unmount();

      expect(removeEventListenerSpy).toHaveBeenCalledWith(
        "platforms-filter-changed",
        expect.any(Function),
      );

      removeEventListenerSpy.mockRestore();
    });
  });
});
