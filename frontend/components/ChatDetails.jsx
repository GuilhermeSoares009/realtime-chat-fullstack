"use client";

import { useState, useEffect, useRef } from "react";
import Loader from "./Loader";
import { useAuth } from '@/contexts/AuthContext';
import MessageBox from "./MessageBox";
import { apiClient } from '@/lib/api-client';
import { getEcho } from '@/lib/pusher';

const ChatDetails = ({ chatId }) => {
  const [loading, setLoading] = useState(true);
  const [chat, setChat] = useState({});
  const [otherMember, setOtherMember] = useState(null);

  const { user: currentUser } = useAuth();

  const [text, setText] = useState("");

  const getChatDetails = async () => {
    try {
      const chat = await apiClient.getChat(chatId);
      const messages = await apiClient.getMessages(chatId);
      setChat(chat);
      const other = (chat.users || chat.members || []).find(m => (m.id ?? m._id) !== currentUser.id);
      setOtherMember({
        id: other?.id ?? other?._id,
        username: other?.username ?? other?.name,
        profileImage: other?.profileImage ?? other?.avatar,
      });
      setMessages(messages.reverse ? messages.reverse() : messages);
      setLoading(false);
    } catch (error) {
      console.log(error);
    }
  };

  useEffect(() => {
    if (currentUser && chatId) getChatDetails();
  }, [currentUser, chatId]);

  const [messages, setMessages] = useState([]);
  const [sending, setSending] = useState(false);

  const sendText = async () => {
    if (!text.trim()) return;
    setSending(true);
    try {
      const message = await apiClient.sendMessage(chatId, text.trim());
      setMessages(prev => [...prev, message]);
      setText("");
    } catch (err) {
      console.log(err);
    } finally {
      setSending(false);
    }
  };

  useEffect(() => {
    const echo = getEcho();
    if (!echo) return;

    echo.private(`chat.${chatId}`)
      .listen('.message.sent', (data) => {
        if (data.message && data.message.sender_id !== currentUser.id) {
          setMessages(prev => [...prev, data.message]);
        }
      })
      .listen('.user.typing', (data) => {
        if (data.user_id !== currentUser.id) {
          setTypingUser(data.is_typing ? data.user_name : null);
        }
      });

    return () => {
      try { echo.leave(`chat.${chatId}`); } catch(e){}
    };
  }, [chatId, currentUser]);

  const bottomRef = useRef(null);
  const [typingUser, setTypingUser] = useState(null);

  let typingTimeout;
  const handleTyping = (e) => {
    setText(e.target.value);
    apiClient.sendTyping(chatId, true).catch(() => {});
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => {
      apiClient.sendTyping(chatId, false).catch(() => {});
    }, 1000);
  };

  useEffect(() => {
    bottomRef.current?.scrollIntoView({
      behavior: "smooth",
    });
  }, [chat?.messages]);

  return loading ? (
    <Loader />
  ) : (
    <div className="pb-20">
      <div className="chat-details">
        <div className="chat-header">
          {otherMember && (
            <>
              <img
                src={avatarUrl(otherMember, 80)}
                alt="profile photo"
                className="profilePhoto"
              />
              <div className="text">
                <p>{otherMember.username}</p>
              </div>
            </>
          )}
        </div>

        <div className="chat-body">
          {messages?.map((message, index) => (
            <MessageBox
              key={message.id ?? index}
              message={message}
              currentUser={currentUser}
            />
          ))}
          <div ref={bottomRef} />
        </div>

        <div className="send-message">
          <div className="prepare-message">
            <input
              type="text"
              placeholder="Write a message..."
              className="input-field"
              value={text}
              onChange={handleTyping}
              required
            />
          </div>

          <div onClick={sendText}>
            <img src="/assets/send.jpg" alt="send" className="send-icon" />
          </div>
        </div>
      </div>
    </div>
  );
};

export default ChatDetails;