const { mount } = require("@vue/test-utils");
const PeopleList = require("../PeopleList.vue");

describe("PeopleList", () => {
  const mockPeople = [
    {
      title: "John Doe",
      url: "/wiki/People/John_Doe",
      image:
        "https://www.giantbomb.com/a/uploads/scale_small/0/3699/2970349-john.jpg",
      deck: "Game developer and designer",
      caption: "John Doe",
      games: ["Game1", "Game2", "Game3"],
    },
    {
      title: "Jane Smith",
      url: "/wiki/People/Jane_Smith",
      image:
        "https://www.giantbomb.com/a/uploads/scale_small/0/3699/2970350-jane.jpg",
      deck: "Video game journalist",
      caption: "Jane Smith",
      games: ["Game1"],
    },
  ];

  const defaultProps = {
    initialData: JSON.stringify(mockPeople),
    totalCount: "2",
    currentPage: "1",
    totalPages: "1",
  };

  let consoleErrorSpy;
  const originalConsoleError = console.error;

  beforeEach(() => {
    // Clear all mocks before each test
    jest.clearAllMocks();

    // Suppress expected "Failed to fetch people" and jsdom navigation errors
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
        // Suppress "Failed to fetch people" errors
        if (
          typeof message === "string" &&
          message.includes("Failed to fetch people")
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
      pathname: "/wiki/People",
      href: "http://localhost:8080/wiki/People",
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
    it("renders people grid", async () => {
      const wrapper = mount(PeopleList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const peopleGrid = wrapper.find(".listing-grid");
      expect(peopleGrid.exists()).toBe(true);

      const peopleCards = wrapper.findAll(".listing-card");
      expect(peopleCards).toHaveLength(2);
    });

    it("displays person information correctly", async () => {
      const wrapper = mount(PeopleList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const firstPerson = wrapper.findAll(".listing-card")[0];
      const title = firstPerson.find(".listing-card-title");
      expect(title.text()).toBe("John Doe");

      const deck = firstPerson.find(".listing-card-deck");
      expect(deck.text()).toBe("Game developer and designer");

      const link = firstPerson.find(".listing-card-link");
      expect(link.attributes("href")).toBe("/wiki/People/John_Doe");
    });

    it("displays person images", async () => {
      const wrapper = mount(PeopleList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const images = wrapper.findAll(".listing-card-image img");
      expect(images).toHaveLength(2);
      expect(images[0].attributes("src")).toBe(
        "https://www.giantbomb.com/a/uploads/scale_small/0/3699/2970349-john.jpg",
      );
      expect(images[0].attributes("alt")).toBe("John Doe");
    });

    it("displays placeholder image when no image is provided", async () => {
      const noImageData = [
        {
          title: "Person Without Image",
          url: "/wiki/People/Person_Without_Image",
          image: null,
          deck: "A person without an image",
          games: [],
        },
      ];

      const wrapper = mount(PeopleList, {
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
      const wrapper = mount(PeopleList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const decks = wrapper.findAll(".listing-card-deck");
      expect(decks).toHaveLength(2);
      expect(decks[0].text()).toBe("Game developer and designer");
    });

    it("does not display deck when not provided", async () => {
      const noDeckData = [
        {
          title: "Person Without Deck",
          url: "/wiki/People/Person_Without_Deck",
          image: "https://example.com/test.jpg",
          deck: null,
          games: [],
        },
      ];

      const wrapper = mount(PeopleList, {
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

    it("displays game count when games are present", async () => {
      const wrapper = mount(PeopleList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const firstPerson = wrapper.findAll(".listing-card")[0];
      const gameCountText = firstPerson.text();
      expect(gameCountText).toContain("Credited in 3 games");
    });

    it("displays singular 'game' when person has one game", async () => {
      const singleGameData = [
        {
          title: "Person With One Game",
          url: "/wiki/People/Person_With_One_Game",
          image: "https://example.com/test.jpg",
          deck: "A person",
          games: ["Game1"],
        },
      ];

      const wrapper = mount(PeopleList, {
        props: {
          initialData: JSON.stringify(singleGameData),
          totalCount: "1",
          currentPage: "1",
          totalPages: "1",
        },
      });

      await wrapper.vm.$nextTick();

      const personCard = wrapper.find(".listing-card");
      const gameCountText = personCard.text();
      expect(gameCountText).toContain("Credited in 1 game");
      expect(gameCountText).not.toContain("games");
    });

    it("displays plural 'games' when person has multiple games", async () => {
      const wrapper = mount(PeopleList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const firstPerson = wrapper.findAll(".listing-card")[0];
      const gameCountText = firstPerson.text();
      expect(gameCountText).toContain("Credited in 3 games");
    });

    it("does not display game count when games array is empty", async () => {
      const noGamesData = [
        {
          title: "Person Without Games",
          url: "/wiki/People/Person_Without_Games",
          image: "https://example.com/test.jpg",
          deck: "A person",
          games: [],
        },
      ];

      const wrapper = mount(PeopleList, {
        props: {
          initialData: JSON.stringify(noGamesData),
          totalCount: "1",
          currentPage: "1",
          totalPages: "1",
        },
      });

      await wrapper.vm.$nextTick();

      const personCard = wrapper.find(".listing-card");
      const gameCountText = personCard.text();
      expect(gameCountText).not.toContain("Credited in");
    });

    it("does not display game count when games property is missing", async () => {
      const noGamesProperty = [
        {
          title: "Person Without Games Property",
          url: "/wiki/People/Person_Without_Games_Property",
          image: "https://example.com/test.jpg",
          deck: "A person",
        },
      ];

      const wrapper = mount(PeopleList, {
        props: {
          initialData: JSON.stringify(noGamesProperty),
          totalCount: "1",
          currentPage: "1",
          totalPages: "1",
        },
      });

      await wrapper.vm.$nextTick();

      const personCard = wrapper.find(".listing-card");
      const gameCountText = personCard.text();
      expect(gameCountText).not.toContain("Credited in");
    });
  });

  describe("Loading State", () => {
    it("displays loading state when fetching people", async () => {
      const wrapper = mount(PeopleList, {
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

      expect(loadingDiv.text()).toContain("Loading people...");
    });

    it("hides people content when loading", async () => {
      const wrapper = mount(PeopleList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Set loading to true
      wrapper.vm.loading = true;
      await wrapper.vm.$nextTick();

      const peopleGrid = wrapper.find(".listing-grid");
      expect(peopleGrid.exists()).toBe(false);
    });
  });

  describe("Empty State", () => {
    it("displays no people message when people array is empty", async () => {
      const wrapper = mount(PeopleList, {
        props: {
          initialData: "[]",
          totalCount: "0",
          currentPage: "1",
          totalPages: "1",
        },
      });

      await wrapper.vm.$nextTick();

      const noPeople = wrapper.find(".listing-empty");
      expect(noPeople.exists()).toBe(true);
      expect(noPeople.text()).toContain(
        "No people found for the selected filters.",
      );
    });
  });

  describe("Pagination", () => {
    it("displays pagination when total pages is greater than 1", async () => {
      const wrapper = mount(PeopleList, {
        props: {
          initialData: JSON.stringify(mockPeople),
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
      const wrapper = mount(PeopleList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // PeopleList always renders the Pagination component
      // The Pagination component itself handles single-page display
      const pagination = wrapper.find(".pagination");
      expect(pagination.exists()).toBe(true);

      // Should show "Showing 1-10 of 10 items" for single page
      const paginationInfo = wrapper.find(".pagination-info");
      expect(paginationInfo.text()).toContain("Showing");
    });

    it("disables previous button on first page", async () => {
      const wrapper = mount(PeopleList, {
        props: {
          initialData: JSON.stringify(mockPeople),
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
      const wrapper = mount(PeopleList, {
        props: {
          initialData: JSON.stringify(mockPeople),
          totalCount: "96", // 96 items / 48 per page = 2 pages
          currentPage: "2",
          totalPages: "2",
        },
      });

      await wrapper.vm.$nextTick();

      const nextButton = wrapper.find(".pagination-next");
      expect(nextButton.attributes("disabled")).toBeDefined();
    });

    it("fetches people when next page is clicked", async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          success: true,
          people: [
            {
              title: "Bob Johnson",
              url: "/wiki/People/Bob_Johnson",
              image: "https://example.com/bob.jpg",
              deck: "Game producer",
              games: [],
            },
          ],
          totalCount: 50,
          currentPage: 2,
          totalPages: 5,
          pageSize: 48,
        }),
      });

      const wrapper = mount(PeopleList, {
        props: {
          initialData: JSON.stringify(mockPeople),
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
        expect.stringContaining("action=get-people"),
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
          people: mockPeople,
          totalCount: 240, // 240 / 48 = 5 pages
          currentPage: 3,
          totalPages: 5,
          pageSize: 48,
        }),
      });

      const wrapper = mount(PeopleList, {
        props: {
          initialData: JSON.stringify(mockPeople),
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
          people: mockPeople,
          totalCount: 50,
          currentPage: 2,
          totalPages: 5,
          pageSize: 48,
        }),
      });

      const wrapper = mount(PeopleList, {
        props: {
          initialData: JSON.stringify(mockPeople),
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
          people: [
            {
              title: "Filtered Person",
              url: "/wiki/People/Filtered_Person",
              image: "https://example.com/filtered.jpg",
              deck: "A filtered person",
              games: [],
            },
          ],
          totalCount: 1,
          currentPage: 1,
          totalPages: 1,
        }),
      });

      const wrapper = mount(PeopleList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      // Dispatch filter change event
      const event = new CustomEvent("people-filter-changed", {
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

    it("updates people after successful fetch", async () => {
      const newPeople = [
        {
          title: "New Person",
          url: "/wiki/People/New_Person",
          image: "https://example.com/new.jpg",
          deck: "A new person",
          games: [],
        },
      ];

      const wrapper = mount(PeopleList, {
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
            people: newPeople,
            totalCount: 1,
            currentPage: 1,
            totalPages: 1,
          }),
        }),
      );

      const event = new CustomEvent("people-filter-changed", {
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
        expect.stringContaining("action=get-people"),
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
      expect(wrapper.vm.items[0].title).toBe("New Person");

      // Verify the DOM was updated
      const peopleCards = wrapper.findAll(".listing-card");
      expect(peopleCards).toHaveLength(1);
      expect(peopleCards[0].text()).toContain("New Person");
    });

    it("handles game title filters", async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          success: true,
          people: mockPeople,
          totalCount: 2,
          currentPage: 1,
          totalPages: 1,
        }),
      });

      const wrapper = mount(PeopleList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const event = new CustomEvent("people-filter-changed", {
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

      const wrapper = mount(PeopleList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const event = new CustomEvent("people-filter-changed", {
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
      const peopleCards = wrapper.findAll(".listing-card");
      expect(peopleCards).toHaveLength(2);

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

      const wrapper = mount(PeopleList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      const event = new CustomEvent("people-filter-changed", {
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
          title: "Person &amp; Test",
          url: "/wiki/People/Person_%26_Test",
          image: "https://example.com/test.jpg",
          deck: "A person &amp; test",
          games: [],
        },
      ];

      const wrapper = mount(PeopleList, {
        props: {
          initialData: JSON.stringify(encodedData),
          totalCount: "1",
          currentPage: "1",
          totalPages: "1",
        },
      });

      await wrapper.vm.$nextTick();

      const title = wrapper.find(".listing-card-title");
      expect(title.text()).toBe("Person & Test");

      const deck = wrapper.find(".listing-card-deck");
      expect(deck.text()).toBe("A person & test");
    });

    it("handles invalid JSON gracefully", async () => {
      const consoleErrorSpy = jest.spyOn(console, "error").mockImplementation();

      const wrapper = mount(PeopleList, {
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

      const noPeople = wrapper.find(".listing-empty");
      expect(noPeople.exists()).toBe(true);

      consoleErrorSpy.mockRestore();
    });
  });

  describe("Event Cleanup", () => {
    it("removes event listener on unmount", async () => {
      const removeEventListenerSpy = jest.spyOn(window, "removeEventListener");

      const wrapper = mount(PeopleList, {
        props: defaultProps,
      });

      await wrapper.vm.$nextTick();

      wrapper.unmount();

      expect(removeEventListenerSpy).toHaveBeenCalledWith(
        "people-filter-changed",
        expect.any(Function),
      );

      removeEventListenerSpy.mockRestore();
    });
  });
});
