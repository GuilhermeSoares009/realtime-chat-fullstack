"use client";

import { useAuth } from '@/contexts/AuthContext';
import { apiClient } from '@/lib/api-client';
import { getEcho } from '@/lib/pusher';
import { useEffect, useState } from "react";
import ChatBox from "./ChatBox";
import Loader from "./Loader";

const ChatList = ({ currentChatId }) => {
  const { user: currentUser } = useAuth();

  const [loading, setLoading] = useState(true);
  const [chats, setChats] = useState([]);
  const [search, setSearch] = useState("");

  const getChats = async () => {
    try {
      const data = await apiClient.getChats();
      // normalize ids
      const normalized = (data || []).map(c => ({
        id: c.id ?? c._id,
        name: c.name,
        users: c.users ?? c.members,
        last_message: c.last_message ?? c.messages?.[c.messages.length - 1],
        unread_count: c.unread_count ?? 0,
        ...c,
      }));
      setChats(normalized);
      setLoading(false);
    } catch (err) {
      console.log(err);
    }
  };

  useEffect(() => {
    if (currentUser) {
      getChats();
    }
  }, [currentUser]);

  // client-side filtering
  useEffect(() => {
    if (search === "") {
      // reload chats
      getChats();
      return;
    }
    const filtered = chats.filter(chat => {
      const otherUser = (chat.users || []).find(u => u.id !== currentUser.id);
      return otherUser?.name?.toLowerCase().includes(search.toLowerCase()) || otherUser?.username?.toLowerCase().includes(search.toLowerCase());
    });
    setChats(filtered);
  }, [search]);

  useEffect(() => {
    const echo = getEcho();
    if (!currentUser || !echo) return;

    // listen for new chats for this user
    echo.private(`user.${currentUser.id}`)
      .listen('.chat.created', (data) => {
        setChats((prev) => [data.chat, ...prev]);
      });

    // listen for messages on each chat
    const subscribeChats = () => {
      chats.forEach(chat => {
        echo.private(`chat.${chat.id}`)
          .listen('.message.sent', (data) => {
            setChats((prev) => prev.map(c => c.id === chat.id ? { ...c, last_message: data.message, unread_count: (c.unread_count || 0) + 1 } : c));
          });
      });
    };

    subscribeChats();

    return () => {
      try {
        echo.leave(`user.${currentUser.id}`);
        chats.forEach(chat => echo.leave(`chat.${chat.id}`));
      } catch (e) {}
    };
  }, [currentUser, chats]);

  return loading ? (
    <Loader />
  ) : (
    <div className="chat-list">
      <input
        placeholder="Search chat..."
        className="input-search"
        value={search}
        onChange={(e) => setSearch(e.target.value)}
      />

      <div className="chats">
        {chats?.map((chat, index) => (
          <ChatBox
            chat={chat}
            index={index}
            currentUser={currentUser}
            currentChatId={currentChatId}
            key={chat.id || index}
          />
        ))}
      </div>
    </div>
  );
};

export default ChatList;