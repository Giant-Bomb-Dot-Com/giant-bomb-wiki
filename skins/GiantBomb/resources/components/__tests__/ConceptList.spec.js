const { mount } = require("@vue/test-utils");
const ConceptList = require("../ConceptList.vue");

describe("ConceptList", () => {
  const mockConcepts = [
    {
      title: "Action",
      url: "/wiki/Concepts/Action",
      image:
        "https://www.giantbomb.com/a/uploads/scale_small/0/3699/2970349-action.jpg",
      deck: "Games focused on physical challenges",
      caption: "Action",
    },
    {
      title: "Adventure",
      url: "/wiki/Concepts/Adventure",
      image:
        "https://www.giantbomb.com/a/uploads/scale_small/0/3699/2970350-adventure.jpg",
      deck: "Games emphasizing exploration and puzzle-solving",
      caption: "Adventure",
    },
  ];

  const defaultProps = {
    initialData: JSON.stringify(mockConcepts),
    totalCount: "2",
    currentPage: "1",
    totalPages: "1",
  };

  let consoleErrorSpy;
  const originalConsoleError = console.error;

  beforeEach(() => {
    // Clear all mocks before each test
    jest.clearAllMocks();

    // Suppress expected "Failed to fetch concepts" and jsdom navigation errors
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
        // Suppress "Failed to fetch concepts" errors
        if (
          typeof message === "string" &&
          message.includes("Failed to fetch concepts")
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
      pathname: "/wiki/Concepts",
      href: "http://localhost:8080/wiki/Concepts",
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
    it("renders concepts grid", async () => {
      const wrapper = mount(ConceptList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const conceptsGrid = wrapper.find(".listing-grid");
      expect(conceptsGrid.exists()).toBe(true);

      const conceptCards = wrapper.findAll(".listing-card");
      expect(conceptCards).toHaveLength(2);
    });

    it("displays concept information correctly", async () => {
      const wrapper = mount(ConceptList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const firstConcept = wrapper.findAll(".listing-card")[0];
      const title = firstConcept.find(".listing-card-title");
      expect(title.text()).toBe("Action");

      const deck = firstConcept.find(".listing-card-deck");
      expect(deck.text()).toBe("Games focused on physical challenges");

      const link = firstConcept.find(".listing-card-link");
      expect(link.attributes("href")).toBe("/wiki/Concepts/Action");
    });

    it("displays concept images", async () => {
      const wrapper = mount(ConceptList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const images = wrapper.findAll(".listing-card-image img");
      expect(images).toHaveLength(2);
      expect(images[0].attributes("src")).toBe(
        "https://www.giantbomb.com/a/uploads/scale_small/0/3699/2970349-action.jpg",
      );
      expect(images[0].attributes("alt")).toBe("Action");
    });

    it("displays placeholder image when no image is provided", async () => {
      const noImageData = [
        {
          title: "Concept Without Image",
          url: "/wiki/Concepts/Concept_Without_Image",
          image: null,
          deck: "A concept without an image",
        },
      ];

      const wrapper = mount(ConceptList, {
        props: {
          initialData: JSON.stringify(noImageData),
          totalCount: "1",
          currentPage: "1",
          totalPages: "1",
        },
      });

      await wrapper.vm.$nextTick();

      const placeholder = wrapper.find(".listing-card-image-placeholder");
      expect(placeholder.exists()).toBe(true);

      const img = placeholder.find("img");
      expect(img.attributes("src")).toContain("gb_default-16_9.png");
    });

    it("displays deck when provided", async () => {
      const wrapper = mount(ConceptList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const decks = wrapper.findAll(".listing-card-deck");
      expect(decks).toHaveLength(2);
      expect(decks[0].text()).toBe("Games focused on physical challenges");
    });

    it("does not display deck when not provided", async () => {
      const noDeckData = [
        {
          title: "Concept Without Deck",
          url: "/wiki/Concepts/Concept_Without_Deck",
          image: "https://example.com/test.jpg",
          deck: null,
        },
      ];

      const wrapper = mount(ConceptList, {
        props: {
          initialData: JSON.stringify(noDeckData),
          totalCount: "1",
          currentPage: "1",
          totalPages: "1",
        },
      });

      await wrapper.vm.$nextTick();

      const deck = wrapper.find(".listing-card-deck");
      expect(deck.exists()).toBe(false);
    });
  });

  describe("Loading State", () => {
    it("displays loading state when fetching concepts", async () => {
      const wrapper = mount(ConceptList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Set loading to true
      wrapper.vm.loading = true;
      await wrapper.vm.$nextTick();

      const loadingDiv = wrapper.find(".listing-loading");
      expect(loadingDiv.exists()).toBe(true);

      const spinner = wrapper.find(".loading-spinner");
      expect(spinner.exists()).toBe(true);

      expect(loadingDiv.text()).toContain("Loading concepts...");
    });

    it("hides concept content when loading", async () => {
      const wrapper = mount(ConceptList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Set loading to true
      wrapper.vm.loading = true;
      await wrapper.vm.$nextTick();

      const conceptsGrid = wrapper.find(".listing-grid");
      expect(conceptsGrid.exists()).toBe(false);
    });
  });

  describe("Empty State", () => {
    it("displays no concepts message when concepts array is empty", async () => {
      const wrapper = mount(ConceptList, {
        props: {
          initialData: "[]",
          totalCount: "0",
          currentPage: "1",
          totalPages: "1",
        },
      });

      await wrapper.vm.$nextTick();

      const noConcepts = wrapper.find(".listing-empty");
      expect(noConcepts.exists()).toBe(true);
      expect(noConcepts.text()).toContain(
        "No concepts found for the selected filters.",
      );
    });
  });

  describe("Pagination", () => {
    it("displays pagination when total pages is greater than 1", async () => {
      const wrapper = mount(ConceptList, {
        props: {
          initialData: JSON.stringify(mockConcepts),
          totalCount: "50",
          currentPage: "2",
          totalPages: "5",
        },
      });

      await wrapper.vm.$nextTick();

      const pagination = wrapper.find(".pagination");
      expect(pagination.exists()).toBe(true);

      const paginationInfo = wrapper.find(".pagination-info");
      // Pagination component displays "Showing X-Y of Z items"
      expect(paginationInfo.text()).toContain("Showing");
      expect(paginationInfo.text()).toContain("of 50 items");
    });

    it("shows pagination even when total pages is 1", async () => {
      const wrapper = mount(ConceptList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // ConceptList always renders the Pagination component
      // The Pagination component itself handles single-page display
      const pagination = wrapper.find(".pagination");
      expect(pagination.exists()).toBe(true);

      // Should show "Showing 1-10 of 10 items" for single page
      const paginationInfo = wrapper.find(".pagination-info");
      expect(paginationInfo.text()).toContain("Showing");
    });

    it("disables previous button on first page", async () => {
      const wrapper = mount(ConceptList, {
        props: {
          initialData: JSON.stringify(mockConcepts),
          totalCount: "50",
          currentPage: "1",
          totalPages: "5",
        },
      });

      await wrapper.vm.$nextTick();

      const prevButton = wrapper.find(".pagination-prev");
      expect(prevButton.attributes("disabled")).toBeDefined();
    });

    it("disables next button on last page", async () => {
      const wrapper = mount(ConceptList, {
        props: {
          initialData: JSON.stringify(mockConcepts),
          totalCount: "96", // 96 items / 48 per page = 2 pages
          currentPage: "2",
          totalPages: "2",
        },
      });

      await wrapper.vm.$nextTick();

      const nextButton = wrapper.find(".pagination-next");
      expect(nextButton.attributes("disabled")).toBeDefined();
    });

    it("fetches concepts when next page is clicked", async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          success: true,
          concepts: [
            {
              title: "RPG",
              url: "/wiki/Concepts/RPG",
              image: "https://example.com/rpg.jpg",
              deck: "Role-playing games",
            },
          ],
          totalCount: 50,
          currentPage: 2,
          totalPages: 5,
          pageSize: 48,
        }),
      });

      const wrapper = mount(ConceptList, {
        props: {
          initialData: JSON.stringify(mockConcepts),
          totalCount: "50",
          currentPage: "1",
          totalPages: "5",
        },
      });

      await wrapper.vm.$nextTick();

      const nextButton = wrapper.find(".pagination-next");
      await nextButton.trigger("click");

      await new Promise((resolve) => setTimeout(resolve, 0));
      await wrapper.vm.$nextTick();

      expect(global.fetch).toHaveBeenCalledWith(
        expect.stringContaining("action=get-concepts"),
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
          concepts: mockConcepts,
          totalCount: 240, // 240 / 48 = 5 pages
          currentPage: 3,
          totalPages: 5,
          pageSize: 48,
        }),
      });

      const wrapper = mount(ConceptList, {
        props: {
          initialData: JSON.stringify(mockConcepts),
          totalCount: "240", // 240 / 48 = 5 pages
          currentPage: "2",
          totalPages: "5",
        },
      });

      await wrapper.vm.$nextTick();

      const nextButton = wrapper.find(".pagination-next");
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
          concepts: mockConcepts,
          totalCount: 50,
          currentPage: 2,
          totalPages: 5,
          pageSize: 48,
        }),
      });

      const wrapper = mount(ConceptList, {
        props: {
          initialData: JSON.stringify(mockConcepts),
          totalCount: "50",
          currentPage: "1",
          totalPages: "5",
        },
      });

      await wrapper.vm.$nextTick();

      const nextButton = wrapper.find(".pagination-next");
      await nextButton.trigger("click");

      await new Promise((resolve) => setTimeout(resolve, 0));
      await wrapper.vm.$nextTick();

      expect(window.scrollTo).toHaveBeenCalledWith({
        top: 0,
        behavior: "smooth",
      });
    });
  });

  describe("Filter Change Handling", () => {
    it("listens for filter change events", async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          success: true,
          concepts: [
            {
              title: "Filtered Concept",
              url: "/wiki/Concepts/Filtered_Concept",
              image: "https://example.com/filtered.jpg",
              deck: "A filtered concept",
            },
          ],
          totalCount: 1,
          currentPage: 1,
          totalPages: 1,
        }),
      });

      const wrapper = mount(ConceptList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Dispatch filter change event
      const event = new CustomEvent("concepts-filter-changed", {
        detail: {
          letter: "A",
          sort: "last_edited",
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
        expect.stringContaining("sort=last_edited"),
        expect.any(Object),
      );
    });

    it("updates concepts after successful fetch", async () => {
      const newConcepts = [
        {
          title: "New Concept",
          url: "/wiki/Concepts/New_Concept",
          image: "https://example.com/new.jpg",
          deck: "A new concept",
        },
      ];

      const wrapper = mount(ConceptList, {
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
            concepts: newConcepts,
            totalCount: 1,
            currentPage: 1,
            totalPages: 1,
          }),
        }),
      );

      const event = new CustomEvent("concepts-filter-changed", {
        detail: {
          letter: "N",
          sort: "last_edited",
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
        expect.stringContaining("action=get-concepts"),
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
      expect(wrapper.vm.items).toHaveLength(1);
      expect(wrapper.vm.items[0].title).toBe("New Concept");

      // Verify the DOM was updated
      const conceptCards = wrapper.findAll(".listing-card");
      expect(conceptCards).toHaveLength(1);
      expect(conceptCards[0].text()).toContain("New Concept");
    });

    it("handles game title filters", async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          success: true,
          concepts: mockConcepts,
          totalCount: 2,
          currentPage: 1,
          totalPages: 1,
        }),
      });

      const wrapper = mount(ConceptList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const event = new CustomEvent("concepts-filter-changed", {
        detail: {
          letter: "",
          sort: "alphabetical",
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

      const wrapper = mount(ConceptList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const event = new CustomEvent("concepts-filter-changed", {
        detail: {
          letter: "",
          sort: "alphabetical",
          game_title: [],
          page: 1,
        },
      });
      window.dispatchEvent(event);

      await new Promise((resolve) => setTimeout(resolve, 0));
      await wrapper.vm.$nextTick();

      expect(consoleErrorSpy).toHaveBeenCalled();

      // Should keep existing data on error
      const conceptCards = wrapper.findAll(".listing-card");
      expect(conceptCards).toHaveLength(2);

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

      const wrapper = mount(ConceptList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const event = new CustomEvent("concepts-filter-changed", {
        detail: {
          letter: "",
          sort: "alphabetical",
          game_title: [],
          page: 1,
        },
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
          title: "Concept &amp; Test",
          url: "/wiki/Concepts/Concept_%26_Test",
          image: "https://example.com/test.jpg",
          deck: "A concept &amp; test",
        },
      ];

      const wrapper = mount(ConceptList, {
        props: {
          initialData: JSON.stringify(encodedData),
          totalCount: "1",
          currentPage: "1",
          totalPages: "1",
        },
      });

      await wrapper.vm.$nextTick();

      const title = wrapper.find(".listing-card-title");
      expect(title.text()).toBe("Concept & Test");

      const deck = wrapper.find(".listing-card-deck");
      expect(deck.text()).toBe("A concept & test");
    });

    it("handles invalid JSON gracefully", async () => {
      const consoleErrorSpy = jest.spyOn(console, "error").mockImplementation();

      const wrapper = mount(ConceptList, {
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

      const noConcepts = wrapper.find(".listing-empty");
      expect(noConcepts.exists()).toBe(true);

      consoleErrorSpy.mockRestore();
    });
  });

  describe("Event Cleanup", () => {
    it("removes event listener on unmount", async () => {
      const removeEventListenerSpy = jest.spyOn(window, "removeEventListener");

      const wrapper = mount(ConceptList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      wrapper.unmount();

      expect(removeEventListenerSpy).toHaveBeenCalledWith(
        "concepts-filter-changed",
        expect.any(Function),
      );

      removeEventListenerSpy.mockRestore();
    });
  });
});
